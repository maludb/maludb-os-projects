<?php
declare(strict_types=1);
/**
 * What every issue handler shares (a small addition to the spec's file list, so no handler repeats it):
 * the issue a request names (id or KEY-n, the manifest's `issue` or the column's `issue_id`), the base
 * row behind the gate, and the one way an action answers — status emitted, `HX-Trigger: issueChanged`
 * (the view's regions re-fetch themselves, Pattern D), JSON told, a plain browser sent back to the issue.
 */
require_once __DIR__ . '/queries.php';

/** The base row of the issue the request names, or a 404 in the handler's own words. */
function issue_from_request(PDO $pdo, string $param = 'issue'): array
{
    $raw = request_string($param) ?: request_string($param . '_id') ?: request_string('key');
    if ($raw === '') {
        refuse(422, 'Which issue?');
    }
    $row = null;
    if (ctype_digit($raw)) {
        $view = find_issue($pdo, (int) $raw);                 // the view first: what the caller may not see is "not found"
        $row = $view === null ? null : find_issue_for_edit($pdo, (int) $raw);
    } else {
        $view = find_issue_by_key($pdo, $raw);
        $row = $view === null ? null : find_issue_for_edit($pdo, (int) $view['issue_id']);
    }
    if ($row === null) {
        refuse(404, 'Issue not found.');
    }
    return $row;
}

/** The action is done: say so, tell the regions, and answer each caller its own way. */
function issue_action_done(string $issueKey, string $did, array $extra = [], string $event = 'issueChanged'): never
{
    emit_action_status(true, ['did' => $did, 'refresh' => $event] + $extra);
    hx_trigger($event);
    if (wants_json()) {
        respond_saved(['did' => $did, 'location' => '/issues/' . $issueKey] + $extra);
    }
    if (is_htmx_request()) {
        http_response_code(204);
        exit;
    }
    redirect('/issues/' . $issueKey);
}

/** A field's changed value, for the log: [before, after] of the keys that differ. */
function issue_changed_fields(array $before, array $after, array $keys): array
{
    $b = [];
    $a = [];
    foreach ($keys as $k) {
        if (($before[$k] ?? null) != ($after[$k] ?? null)) {
            $b[$k] = $before[$k] ?? null;
            $a[$k] = $after[$k] ?? null;
        }
    }
    return [$b, $a];
}
