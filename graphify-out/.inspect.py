import json, collections, hashlib
from pathlib import Path

g = json.load(open('graphify-out/graph.json', encoding='utf-8'))
nodes = g['nodes'] if isinstance(g, dict) else g
print('graph nodes', len(nodes))
ext = collections.Counter(Path(n.get('source_file') or '').suffix.lower() for n in nodes)
print('by ext', ext.most_common(12))

m = json.load(open('graphify-out/manifest.json', encoding='utf-8'))
inc = json.load(open('graphify-out/.graphify_incremental.json', encoding='utf-8'))
root = 'D:\\Development\\amal\\digital-distribution-management-platform\\'

def rel(f):
    f = f.replace(root, '').replace('\\', '/')
    return f

# which files are marked changed
for cat in ('code', 'document'):
    changed = inc['new_files'].get(cat, [])
    inman = sum(1 for f in changed if rel(f) in m)
    print(cat, 'changed', len(changed), 'in-manifest', inman)

# check a few changed code files
for f in inc['new_files']['code'][:8]:
    r = rel(f)
    print('  ', r, 'IN' if r in m else 'NOT-IN', m.get(r, {}).get('semantic_hash', '')[:8] if r in m else '')

# Does manifest contain the changed code files at all?
print('manifest keys sample', list(m.keys())[:5])
