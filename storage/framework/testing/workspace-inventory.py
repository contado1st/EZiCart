import re
import json
from pathlib import Path

root = Path.cwd()
view_root = root / 'resources/views'
excluded = {'seller/orders/waybill.blade.php', 'seller/products/show.blade.php', 'buyer/for-you.blade.php'}
groups = ('admin/', 'seller/', 'buyer/', 'courier/', 'logistics/', 'account/', 'analytics/', 'finance/', 'reports/', 'orders/', 'notifications/')
inventory = []
for path in sorted(view_root.rglob('*.blade.php')):
    rel = path.relative_to(view_root).as_posix()
    text = path.read_text(encoding='utf-8')
    if not rel.startswith(groups) or rel in excluded:
        continue
    title = re.search(r'<h1[^>]*>(.*?)</h1>', text, re.S)
    layout = re.search(r"@extends\((.*?)\)", text)
    inventory.append({
        'view': rel,
        'layout': layout.group(1) if layout else 'partial',
        'title': re.sub(r'<[^>]+>', '', title.group(1)).strip() if title else 'Inherited / partial',
        'tables': len(re.findall(r'<table\b', text)),
        'forms': len(re.findall(r'<form\b', text)),
        'unassociated_labels': len(re.findall(r'<label(?![^>]*\bfor=)[^>]*>[^<]+</label>', text)),
        'actions': re.findall(r'<form\b[^>]*\baction="(.*?)"', text, re.S),
    })
(root/'storage/framework/testing/workspace-inventory.json').write_text(json.dumps(inventory, indent=2), encoding='utf-8')
for row in inventory:
    print(f"{row['view']} | {row['layout']} | {row['title']} | tables={row['tables']} forms={row['forms']} labels={row['unassociated_labels']}")
print(f'Total: {len(inventory)} templates (including partials).')
