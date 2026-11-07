<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['log']));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['log']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
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
?>
<section <?php echo e($attributes->class(['activity-detail-panel'])); ?> aria-labelledby="activity-detail-title-<?php echo e($log->id); ?>">
    <header class="activity-detail-heading">
        <div><h2 id="activity-detail-title-<?php echo e($log->id); ?>"><?php echo e(\Illuminate\Support\Str::headline($log->action)); ?></h2><p>Log #<?php echo e($log->id); ?> · <?php if(!$before['valid'] || !$after['valid']): ?> Comparison unavailable <?php else: ?> <?php echo e(count($differences)); ?> <?php echo e(count($differences) === 1 ? 'recorded difference' : 'recorded differences'); ?> <?php endif; ?></p></div>
        <span class="activity-record-tag"><?php echo e(class_basename($log->entity_type)); ?> #<?php echo e($log->entity_id); ?></span>
    </header>
    <dl class="activity-log-metadata">
        <div><dt>Recorded at</dt><dd><?php echo e($log->created_at); ?></dd></div>
        <div><dt>Performed by</dt><dd><?php echo e($log->actor_id ? ($actorName ?: 'User').' (#'.$log->actor_id.')' : 'System'); ?><?php if($log->role): ?><small><?php echo e(\Illuminate\Support\Str::headline($log->role)); ?></small><?php endif; ?></dd></div>
        <div><dt>Activity code</dt><dd><code><?php echo e($log->action); ?></code></dd></div>
        <div><dt>Record type</dt><dd><?php echo e($log->entity_type); ?></dd></div>
    </dl>
    <div class="activity-reason"><h3>Recorded reason</h3><p><?php echo e(filled($log->reason) ? $log->reason : 'No reason was recorded.'); ?></p></div>
    <?php if(!$before['valid'] || !$after['valid']): ?>
        <p class="activity-data-notice">A snapshot could not be read as JSON. View the recorded snapshots below.</p>
    <?php else: ?>
        <?php if(count($differences)): ?>
            <div class="activity-diff-heading" aria-hidden="true"><span>Field</span><span>Before</span><span>After</span></div>
            <dl class="activity-differences">
                <?php $__currentLoopData = $differences; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="activity-diff-row is-<?php echo e($row['kind']); ?>">
                        <dt><strong><?php echo e(\Illuminate\Support\Str::headline($row['key'])); ?></strong><small><?php echo e(['changed' => 'Changed', 'added' => 'Only in after', 'removed' => 'Only in before'][$row['kind']]); ?></small></dt>
                        <dd class="activity-value activity-value--before"><span class="activity-value-label">Before</span><div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['activity-structured-value' => is_array($row['old']) || is_object($row['old'])]); ?>"><?php echo e($display($row['old'], $row['had'], $row['key'])); ?></div></dd>
                        <dd class="activity-value activity-value--after"><span class="activity-value-label">After</span><div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['activity-structured-value' => is_array($row['new']) || is_object($row['new'])]); ?>"><?php echo e($display($row['new'], $row['has'], $row['key'])); ?></div></dd>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </dl>
        <?php else: ?>
            <p class="activity-data-notice">No field differences were recorded in these snapshots.</p>
        <?php endif; ?>
        <?php if(count($unchanged)): ?>
            <details class="activity-unchanged"><summary><?php echo e(count($unchanged)); ?> unchanged <?php echo e(count($unchanged) === 1 ? 'field' : 'fields'); ?></summary><dl><?php $__currentLoopData = $unchanged; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><dt><?php echo e(\Illuminate\Support\Str::headline($row['key'])); ?></dt><dd><?php echo e($display($row['old'], true, $row['key'])); ?></dd></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></dl></details>
        <?php endif; ?>
    <?php endif; ?>
    <p class="activity-snapshot-note">Snapshots contain the fields recorded for this event. “Not recorded” does not mean a value was deleted.</p>
    <details class="activity-snapshots"><summary>View recorded snapshots (JSON)</summary><div class="activity-snapshot-grid">
        <?php $__currentLoopData = ['Before' => ['snapshot' => $before, 'raw' => $log->previous_values], 'After' => ['snapshot' => $after, 'raw' => $log->new_values]]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <section><h3><?php echo e($label); ?></h3><pre><?php echo e($item['snapshot']['missing'] ? 'No snapshot recorded.' : ($item['snapshot']['valid'] ? json_encode($item['snapshot']['value'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $item['raw'])); ?></pre></section>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div></details>
</section>
<?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/components/activity-changes.blade.php ENDPATH**/ ?>