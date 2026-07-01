import json, sys
path = sys.argv[1]
nb = json.load(open(path, encoding='utf-8'))
print('cells:', len(nb['cells']))
for i, c in enumerate(nb['cells']):
    lang = c.get('metadata', {}).get('language', '')
    src = ''.join(c['source']).replace('\n', ' / ')[:160]
    print(f"{i:3d} {c['cell_type']:8s} {lang:9s} | {src}")