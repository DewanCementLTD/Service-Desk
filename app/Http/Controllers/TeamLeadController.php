<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\TeamAssignment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Team Lead management — assign and manage team members.
 * Only accessible to Team Leads and administrators.
 */
class TeamLeadController extends Controller
{
    public function __construct(private AuditLogger $audit)
    {
    }

    /**
     * Show team members for the current user (if they are a Team Lead).
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        abort_unless($user->hasRole(Role::TEAM_LEAD) || $user->hasRole(Role::ADMIN), 403,
            'Only Team Leads can access team management.');

        $teamMembers = $user->hasRole(Role::ADMIN)
            ? TeamAssignment::with(['teamLead', 'teamMember', 'assignedBy'])
                ->orderBy('created_at', 'desc')
                ->paginate(25)
            : $user->teamLeadAssignments()
                ->with(['teamMember', 'assignedBy'])
                ->orderBy('created_at', 'desc')
                ->paginate(25);

        return view('team-lead.team-members.index', [
            'teamMembers' => $teamMembers,
            'isAdmin'     => $user->hasRole(Role::ADMIN),
        ]);
    }

    /**
     * Show form to add a team member.
     *
     * An administrator manages everybody's teams rather than one of their own,
     * so they name the team lead on the form and see every IT staff member;
     * a team lead only ever builds their own team and the lead is implied.
     */
    public function create(Request $request): View
    {
        $user = $request->user();

        abort_unless($user->hasRole(Role::TEAM_LEAD) || $user->hasRole(Role::ADMIN), 403,
            'Only Team Leads can assign team members.');

        $isAdmin = $user->hasRole(Role::ADMIN);

        // Get available users to assign (active IT_SUPPORT staff who don't already have this team lead)
        $availableUsers = User::active()
            ->where('id', '!=', $user->id)
            // Only IT_SUPPORT team members
            ->whereHas('role', fn ($q) => $q->where('code', Role::IT_STAFF))
            ->when(! $isAdmin, fn ($q) => $q
                ->whereNotIn('id', $user->teamLeadAssignments()->pluck('team_member_id')->all())
                // Only from the same company
                ->where('default_company_id', $user->default_company_id))
            ->orderBy('name')
            ->get();

        return view('team-lead.team-members.create', [
            'availableUsers' => $availableUsers,
            'isAdmin'        => $isAdmin,
            'teamLeads'      => $isAdmin
                ? User::active()
                    ->whereHas('role', fn ($q) => $q->where('code', Role::TEAM_LEAD))
                    ->orderBy('name')
                    ->get()
                : collect(),
        ]);
    }

    /**
     * Assign a team member to a Team Lead — the signed-in one, or the lead an
     * administrator named on the form.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->hasRole(Role::TEAM_LEAD) || $user->hasRole(Role::ADMIN), 403,
            'Only Team Leads can assign team members.');

        $isAdmin = $user->hasRole(Role::ADMIN);

        $data = $request->validate([
            'team_member_id' => ['required', 'integer', 'exists:oracle.users,id'],
            'team_lead_id'   => [$isAdmin ? 'required' : 'nullable', 'integer', 'exists:oracle.users,id'],
        ]);

        // A team lead may only build their own team, whatever the form posted.
        $lead = $isAdmin ? User::findOrFail((int) $data['team_lead_id']) : $user;

        abort_unless($lead->hasRole(Role::TEAM_LEAD), 422,
            'Team members can only be assigned to a Team Lead.');

        $memberId = (int) $data['team_member_id'];

        // Prevent assigning the lead to themselves
        abort_if($memberId === (int) $lead->id, 422,
            'A Team Lead cannot be a member of their own team.');

        // Check if already assigned
        $exists = TeamAssignment::where('team_lead_id', $lead->id)
            ->where('team_member_id', $memberId)
            ->exists();

        abort_if($exists, 422, "This user is already assigned to {$lead->name}'s team.");

        $member = User::findOrFail($memberId);

        // Verify member is IT_STAFF role
        abort_unless($member->hasRole(Role::IT_STAFF), 422,
            'Only IT Support Staff can be added to a team.');

        // Verify member is from the same company as the lead
        abort_unless($member->default_company_id === $lead->default_company_id, 422,
            'A team member must belong to the same company as their Team Lead.');

        $assignment = TeamAssignment::create([
            'team_lead_id'  => $lead->id,
            'team_member_id' => $memberId,
            'assigned_at'   => now(),
            'assigned_by'   => $user->id,
        ]);

        $this->audit->created('TEAM_ASSIGNMENT', $assignment->id, $assignment->getAttributes(),
            "User {$member->name} assigned to team lead {$lead->name}");

        return redirect()->route('team-lead.index')
            ->with('success', $isAdmin
                ? "{$member->name} has been added to {$lead->name}'s team."
                : "{$member->name} has been added to your team.");
    }

    /**
     * Remove a team member from the Team Lead's team.
     */
    public function destroy(Request $request, TeamAssignment $assignment): RedirectResponse
    {
        $user = $request->user();

        // Only the team lead or admin can remove assignments
        abort_unless(
            $user->id === (int) $assignment->team_lead_id || $user->hasRole(Role::ADMIN),
            403,
            'You do not have permission to remove this team member.'
        );

        $memberName = $assignment->teamMember->name;
        $teamLeadName = $assignment->teamLead->name;

        $assignment->delete();

        $this->audit->deleted('TEAM_ASSIGNMENT', $assignment->id, [],
            "User {$memberName} removed from team lead {$teamLeadName}");

        return back()->with('success', "{$memberName} has been removed from the team.");
    }
}
