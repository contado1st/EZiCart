import re
from pathlib import Path

views = Path('resources/views')
targets = [
    'seller/products/create.blade.php', 'seller/products/edit.blade.php',
    'seller/vouchers/index.blade.php', 'seller/reports/index.blade.php',
    'admin/announcements/index.blade.php', 'admin/reports/index.blade.php',
    'admin/disputes/show.blade.php', 'buyer/disputes/create.blade.php',
]
for relative in targets:
    path = views / relative
    text = path.read_text(encoding='utf-8')
    prefix = relative.replace('.blade.php', '').replace('/', '-')
    count = [0]
    def associate(match):
        attrs, label, space, tag, field = match.groups()
        if re.search(r'\bfor\s*=', attrs):
            return match.group(0)
        name = re.search(r'\bname="([\w]+)"', field)
        if not name:
            return match.group(0)
        field_id = re.search(r'\bid="([^"]+)"', field)
        identifier = field_id.group(1) if field_id else f'{prefix}-{name.group(1)}'
        if not field_id:
            field = f' id="{identifier}"' + field
        count[0] += 1
        return f'<label{attrs} for="{identifier}">{label}</label>{space}<{tag}{field}>'
    text = re.sub(r'<label([^>]*)>([^<]+)</label>(\s*)<(input|select|textarea)([^>]*?)>', associate, text)
    path.write_text(text, encoding='utf-8')
    print(f'{relative}: {count[0]} label associations')

# Static page headers share one accessible, responsive component. Dynamic/detail titles retain
# their source markup and receive the same shared visual contract from workspace-ui.css.
pattern = re.compile(r'<div class="dash-header">\s*<div>\s*<h1 class="dash-title">([^<{]+)</h1>\s*<p class="dash-subtitle">([^<{}]+)</p>\s*</div>\s*</div>')
for folder in ['admin', 'seller', 'buyer']:
    for path in (views / folder).rglob('*.blade.php'):
        if path.name == 'waybill.blade.php' or path.name == 'for-you.blade.php':
            continue
        text = path.read_text(encoding='utf-8')
        def header(match):
            title, description = [' '.join(part.split()) for part in match.groups()]
            if '"' in title + description:
                return match.group(0)
            return f'<x-workspace-page-header title="{title}" description="{description}" />'
        revised, count = pattern.subn(header, text)
        if count:
            path.write_text(revised, encoding='utf-8')
            print(f'{path}: {count} shared page header')
