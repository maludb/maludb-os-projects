<?php
declare(strict_types=1);
/** A row of my work as the JSON branch and the tool show it (the shared shape, nothing more). */
function present_my_work_row(array $r): array
{
    return ['kind' => $r['kind'], 'id' => $r['id'], 'title' => $r['title'], 'detail' => $r['detail'], 'due_at' => $r['due_at'], 'state' => $r['state'], 'href' => $r['href'], 'urgency' => $r['urgency']];
}
function my_work_state_tone(string $state): string
{
    return match ($state) { 'blocked', 'overdue' => 'danger', 'due today' => 'warning', 'due' => 'info', 'in review' => 'primary', default => 'secondary' };
}
