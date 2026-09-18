/**
 * Render the REAL GwSkeleton.tsx through real Mithril.
 *
 * Exists because 0.2.3 shipped a skeleton that threw out of m.render, which
 * blanked every single-giveaway page for every visitor — and nothing in the
 * build had any opinion about it. `npm run build` compiles a component that
 * cannot render just as happily as one that can.
 *
 * Run it against another checkout by passing a path:
 *     node test/render-skeletons.js /path/to/js
 *
 * 🚨 Deliberately not a copy of the component's markup. A test written against
 * a re-typed version of the shape proves that the shape I typed is fine, which
 * is not the question — the question is whether the file that ships renders.
 * So the source file is transpiled and imported as-is, with only the one
 * Flarum import stubbed.
 */
const path = require('path');
const { JSDOM } = require('jsdom');
const ROOT = process.argv[2] || path.join(__dirname, '..');
const ts = require(path.join(ROOT, 'node_modules/typescript'));
const fs = require('fs');

const dom = new JSDOM('<!doctype html><html><body></body></html>');
global.window = dom.window;
global.document = dom.window.document;
global.requestAnimationFrame = (cb) => setTimeout(cb, 0);

const m = require('mithril');
global.m = m;

// The only Flarum import the file makes. Flarum's Component is a Mithril
// class component; this is the part of it these skeletons actually use.
class FlarumComponent {
  oninit(vnode) { this.attrs = vnode.attrs; }
  view() { return null; }
}

const src = fs.readFileSync(
  path.join(ROOT, 'src/forum/components/GwSkeleton.tsx'), 'utf8');
const js = ts.transpileModule(src, {
  compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2019, jsx: 'react' },
}).outputText;

const Module = require('module');
const mod = new Module('GwSkeleton');
mod.require = (id) =>
  id === 'flarum/common/Component' ? { default: FlarumComponent } : require(id);
mod._compile(js, 'GwSkeleton.js');

const { default: GwSkeleton, GwDetailSkeleton } = mod.exports;

function render(label, Comp) {
  const root = document.createElement('div');
  document.body.appendChild(root);
  try {
    m.render(root, m(Comp));
    const html = root.innerHTML;
    console.log(`  ok    ${label} — ${html.length} chars, ` +
                `${root.querySelectorAll('[class^="GwSkeleton"]').length} nodes`);
    return true;
  } catch (e) {
    console.log(`  FAIL  ${label} — ${e.constructor.name}: ${e.message}`);
    return false;
  }
}

// localStorage is absent in this environment on purpose: the list skeleton has
// to survive that, and it is the same path a private-mode browser takes.
const results = [
  render('GwSkeleton (the list)', GwSkeleton),
  render('GwDetailSkeleton (the single giveaway page)', GwDetailSkeleton),
];
process.exit(results.every(Boolean) ? 0 : 1);
