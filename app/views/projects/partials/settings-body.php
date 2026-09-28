<?php /** The chosen settings tab. Data: project, tab, canAdmin, tz, + the tab's own data */
switch ($tab) {
    case 'workflow': echo view('projects/partials/settings-workflow.php', ['project' => $project, 'workflow' => $workflow, 'errors' => $errors, 'canAdmin' => $canAdmin]); break;
    case 'board': echo view('projects/partials/settings-board.php', ['project' => $project, 'config' => $config, 'canAdmin' => $canAdmin]); break;
    case 'team': echo view('projects/partials/settings-team.php', ['project' => $project, 'team' => $team, 'candidates' => $candidates, 'canAdmin' => $canAdmin, 'tz' => $tz]); break;
    case 'labels': echo $labelsHtml; break;
    default: echo view('projects/partials/settings-general.php', ['project' => $project, 'record' => $record, 'departments' => $departments, 'members' => $members, 'errors' => $errors, 'canAdmin' => $canAdmin]);
}
