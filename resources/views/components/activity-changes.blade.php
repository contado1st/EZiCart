@props(['log'])
@php
    $decode = static function ($raw) {
        if ($raw === null) return ['value' => null, 'valid' => true, 'missing' => true];
        $value = json_decode($raw);
        return ['value' => $value, 'valid' => json_last_error() === JSON_ERROR_NONE, 'missing' => false];
    };
    $before = $decode($log->previous_values);
    $after = $decode($log->new_values);
    $fields = static function ($snapshot) {
        if (!$snapshot['valid'] || $snapshot['missing'] || $snapshot['value'] === []) return [];
        return $snapshot['value'] instanceof \stdClass ? get_object_vars($snapshot['value']) : ['value' => $snapshot['value']];
    };
    $oldFields = $fields($before);
    $newFields = $fields($after);
    $normalize = static function ($value) use (&$normalize) {
        if ($value instanceof \stdClass) {
            $properties = get_object_vars($value);
            ksort($properties);
            return (object) array_map($normalize, $properties);
        }
        return is_array($value) ? array_map($normalize, $value) : $value;
    };
    $rows = [];
    foreach (array_unique(array_merge(array_keys($oldFields), array_keys($newFields))) as $key) {
        $had = array_key_exists($key, $oldFields);
        $has = array_key_exists($key, $newFields);
        $same = $had && $has && json_encode($normalize($oldFields[$key]), JSON_PRESERVE_ZERO_FRACTION) === json_encode($normalize($newFields[$key]), JSON_PRESERVE_ZERO_FRACTION);
        $rows[] = ['key' => $key, 'had' => $had, 'has' => $has, 'old' => $oldFields[$key] ?? null, 'new' => $newFields[$key] ?? null,
            'kind' => $same ? 'unchanged' : (!$had ? 'added' : (!$has ? 'removed' : 'changed'))];
    }
    $differences = array_filter($rows, static fn ($row) => $row['kind'] !== 'unchanged');
    $unchanged = array_filter($rows, static fn ($row) => $row['kind'] === 'unchanged');
    $display = static function ($value, $recorded, $key) {
        if (!$recorded) return 'Not recorded';
        if ($value === null) return 'Not set (null)';
        if (is_bool($value)) return $value ? 'Yes' : 'No';
        if (is_string($value) && $value === '') return 'Empty text';
        if (is_array($value) || is_object($value)) return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($value) && ($key === 'status' || str_ends_with($key, '_status') || $key === 'role')) return \Illuminate\Support\Str::headline(strtolower($value));
        return (string) $value;
    };
    $actorName = trim(($log->first_name ?? '').' '.($log->last_name ?? ''));
@endphp
<section {{ $attributes->class(['activity-detail-panel']) }} aria-labelledby="activity-detail-title-{{ $log->id }}">
    <header class="activity-detail-heading">
        <div><h2 id="activity-detail-title-{{ $log->id }}">{{ \Illuminate\Support\Str::headline($log->action) }}</h2><p>Log #{{ $log->id }} · @if(!$before['valid'] || !$after['valid']) Comparison unavailable @else {{ count($differences) }} {{ count($differences) === 1 ? 'recorded difference' : 'recorded differences' }} @endif</p></div>
        <span class="activity-record-tag">{{ class_basename($log->entity_type) }} #{{ $log->entity_id }}</span>
    </header>
    <dl class="activity-log-metadata">
        <div><dt>Recorded at</dt><dd>{{ $log->created_at }}</dd></div>
        <div><dt>Performed by</dt><dd>{{ $log->actor_id ? ($actorName ?: 'User').' (#'.$log->actor_id.')' : 'System' }}@if($log->role)<small>{{ \Illuminate\Support\Str::headline($log->role) }}</small>@endif</dd></div>
        <div><dt>Activity code</dt><dd><code>{{ $log->action }}</code></dd></div>
        <div><dt>Record type</dt><dd>{{ $log->entity_type }}</dd></div>
    </dl>
    <div class="activity-reason"><h3>Recorded reason</h3><p>{{ filled($log->reason) ? $log->reason : 'No reason was recorded.' }}</p></div>
    @if(!$before['valid'] || !$after['valid'])
        <p class="activity-data-notice">A snapshot could not be read as JSON. View the recorded snapshots below.</p>
    @else
        @if(count($differences))
            <div class="activity-diff-heading" aria-hidden="true"><span>Field</span><span>Before</span><span>After</span></div>
            <dl class="activity-differences">
                @foreach($differences as $row)
                    <div class="activity-diff-row is-{{ $row['kind'] }}">
                        <dt><strong>{{ \Illuminate\Support\Str::headline($row['key']) }}</strong><small>{{ ['changed' => 'Changed', 'added' => 'Only in after', 'removed' => 'Only in before'][$row['kind']] }}</small></dt>
                        <dd class="activity-value activity-value--before"><span class="activity-value-label">Before</span><div @class(['activity-structured-value' => is_array($row['old']) || is_object($row['old'])])>{{ $display($row['old'], $row['had'], $row['key']) }}</div></dd>
                        <dd class="activity-value activity-value--after"><span class="activity-value-label">After</span><div @class(['activity-structured-value' => is_array($row['new']) || is_object($row['new'])])>{{ $display($row['new'], $row['has'], $row['key']) }}</div></dd>
                    </div>
                @endforeach
            </dl>
        @else
            <p class="activity-data-notice">No field differences were recorded in these snapshots.</p>
        @endif
        @if(count($unchanged))
            <details class="activity-unchanged"><summary>{{ count($unchanged) }} unchanged {{ count($unchanged) === 1 ? 'field' : 'fields' }}</summary><dl>@foreach($unchanged as $row)<div><dt>{{ \Illuminate\Support\Str::headline($row['key']) }}</dt><dd>{{ $display($row['old'], true, $row['key']) }}</dd></div>@endforeach</dl></details>
        @endif
    @endif
    <p class="activity-snapshot-note">Snapshots contain the fields recorded for this event. “Not recorded” does not mean a value was deleted.</p>
    <details class="activity-snapshots"><summary>View recorded snapshots (JSON)</summary><div class="activity-snapshot-grid">
        @foreach(['Before' => ['snapshot' => $before, 'raw' => $log->previous_values], 'After' => ['snapshot' => $after, 'raw' => $log->new_values]] as $label => $item)
            <section><h3>{{ $label }}</h3><pre>{{ $item['snapshot']['missing'] ? 'No snapshot recorded.' : ($item['snapshot']['valid'] ? json_encode($item['snapshot']['value'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $item['raw']) }}</pre></section>
        @endforeach
    </div></details>
</section>
