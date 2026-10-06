const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const Module = require('node:module');
const ts = require('typescript');
const { reactive } = require('vue');

function loadTs(relative) {
    const filename = path.resolve(relative);
    const code = ts.transpileModule(fs.readFileSync(filename, 'utf8'), {
        compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
    }).outputText;
    const mod = new Module(filename, module);
    mod.filename = filename;
    mod.paths = Module._nodeModulePaths(path.dirname(filename));
    mod._compile(code, filename);
    return mod.exports;
}

const { formatDate, formatDateTime } = loadTs('resources/js/lib/dates.ts');
const { useInlineClientOptions } = loadTs('resources/js/lib/inline-client.ts');

test('date-only values never move to the previous day', () => {
    assert.equal(formatDate('2026-10-05'), '05/10/2026');
    assert.equal(formatDate('2026-01-01'), '01/01/2026');
    assert.equal(formatDate(null), '—');
    assert.equal(formatDate('invalid'), '—');
});

test('timezone-less Web times stay intact and ISO instants use the system timezone', () => {
    assert.equal(formatDateTime('2026-10-05T10:30'), '05/10/2026 - 10:30h');
    assert.equal(formatDateTime('2026-10-05 10:30:00'), '05/10/2026 - 10:30h');
    assert.equal(formatDateTime('2026-10-05T13:30:00Z'), '05/10/2026 - 10:30h');
    assert.equal(formatDateTime('2026-10-05T10:30:00-03:00'), '05/10/2026 - 10:30h');
    assert.equal(formatDateTime('2026-10-05T01:30:00Z'), '04/10/2026 - 22:30h');
    assert.equal(formatDateTime('2026-10-05T03:00:00Z'), '05/10/2026 - 00:00h');
});

test('adding an inline client selects it while preserving all contract draft data', () => {
    const draft = reactive({client_id:1, worksite_address:'Worksite', notes:'Keep notes', started_at:'2026-10-05T10:30', items:[{product_id:9,unit_price:'0.60',initial_quantity:3}], initial_freight:{quantity:2,unit_amount:'10.00'}});
    const before = structuredClone({...draft, items:[{...draft.items[0]}], initial_freight:{...draft.initial_freight}});
    const existing = reactive([{id:1,name:'Existing'}]);
    const { options, created } = useInlineClientOptions(() => existing, id => {draft.client_id = id});
    created({id:2,name:'New client'});
    assert.equal(draft.client_id, 2);
    assert.deepEqual({...draft,client_id:1}, before);
    assert.equal(options.value.find(client => client.id === 2).name, 'New client');
    existing.push({id:2,name:'New client'});
    assert.equal(options.value.filter(client => client.id === 2).length, 1);
});

test('sidebar has a single clickable users item and no administration link', () => {
    const sidebar = fs.readFileSync('resources/js/components/AppSidebar.vue', 'utf8');

    assert.equal((sidebar.match(/title: 'Usuários'/g) || []).length, 1);
    assert.equal((sidebar.match(/href: '\/usuarios'/g) || []).length, 1);
    assert.equal(sidebar.includes("title: 'Administração'"), false);
});
