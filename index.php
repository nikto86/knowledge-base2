<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>База знаний 2</title>
<style>
    body { font-family: Arial, sans-serif; }
    table { border-collapse: collapse; width: 100%; table-layout: fixed; }
    th, td { border: 1px solid #ccc; padding: 6px; vertical-align: top; }
    #menuCol div { padding: 4px; cursor: pointer; }
    #menuCol .active { background: #ffe58f; }
    .item-row { display: flex; align-items: center; justify-content: space-between; gap: 6px; }
    .item-title { cursor: pointer; }
    .actions button { margin-left: 4px; }
    .badge { display:inline-block; padding:2px 6px; border:1px solid #aaa; border-radius: 4px; margin:2px 4px 0 0; }
    .field { margin: 6px 0; }
    .field label { display: block; font-size: 12px; color:#555; margin-bottom: 2px; }
    .field input[type="text"], .field input[type="number"] { width: 100%; box-sizing: border-box; }
    .chips { display:flex; flex-wrap: wrap; gap:4px; }
    .danger { color:#b30000; }
    .muted { color:#777; }
    .toolbar { margin-bottom: 8px; }
    .btn { padding:4px 8px; cursor:pointer; }
    .btn.primary { background:#1677ff; color:#fff; border:none; }
    .btn.ghost { background:transparent; border:1px solid #ccc; }
    .btn.danger { background:#ff4d4f; color:#fff; border:none; }
</style>
</head>
<body>

<table>
    <thead>
    <tr>
        <th>Меню</th>
        <th>Список</th>
        <th colspan="2">Содержимое</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td id="menuCol"></td>
        <td id="listCol"></td>
        <td id="contentCol" colspan="2"></td>
    </tr>
    </tbody>
</table>

<script>
const api = {
    get: async (action, params={}) => {
        const qs = new URLSearchParams(params).toString();
        const r = await fetch('ajax.php?action='+encodeURIComponent(action)+(qs?'&'+qs:''), {headers:{'Accept':'application/json'}});
        const j = await r.json();
        if (!j.ok) throw new Error(j.error||'Ошибка');
        return j.data;
    },
    post: async (action, body={}) => {
        const r = await fetch('ajax.php?action='+encodeURIComponent(action), {
            method: 'POST',
            headers: {'Content-Type':'application/json','Accept':'application/json'},
            body: JSON.stringify(body)
        });
        const j = await r.json();
        if (!j.ok) throw new Error(j.error||'Ошибка');
        return j.data;
    }
};

let state = {
    menu: 'deals',
    list: [],
    options: {deals:[], contacts:[]},
    selectedId: null,
    selectedItem: null
};

function el(tag, attrs={}, children=[]) {
    const e = document.createElement(tag);
    for (const [k,v] of Object.entries(attrs)) {
        if (k === 'class') e.className = v;
        else if (k.startsWith('on') && typeof v==='function') e.addEventListener(k.slice(2), v);
        else if (k==='html') e.innerHTML = v;
        else e.setAttribute(k, v);
    }
    (Array.isArray(children)?children:[children]).forEach(ch => {
        if (ch==null) return;
        if (typeof ch==='string') e.appendChild(document.createTextNode(ch));
        else e.appendChild(ch);
    });
    return e;
}

function renderMenu() {
    const menuCol = document.getElementById('menuCol');
    menuCol.innerHTML = '';
    ['deals','contacts'].forEach(m => {
        menuCol.appendChild(el('div', {
            class: state.menu===m ? 'active' : '',
            onclick: async ()=> { await switchMenu(m); }
        }, m==='deals'?'Сделки':'Контакты'));
    });
}

function renderList() {
    const listCol = document.getElementById('listCol');
    listCol.innerHTML = '';

    const addBtn = el('button', {class:'btn primary', onclick: ()=> openCreateForm()}, '➕ Добавить');
    listCol.appendChild(el('div', {class:'toolbar'}, [addBtn]));

    state.list.forEach(item => {
        const title = state.menu==='deals' ? item.name : (item.first_name + ' ' + (item.last_name||'')).trim();
        const row = el('div', {class:'item-row'});
        const titleEl = el('span', {class:'item-title', onclick: ()=> selectItem(item.id)}, title);
        const actions = el('span', {class:'actions'}, [
            el('button', {class:'btn ghost', onclick: ()=> openEditForm(item.id)}, '✏️'),
            el('button', {class:'btn danger', onclick: ()=> onDelete(item.id)}, '🗑')
        ]);
        row.appendChild(titleEl);
        row.appendChild(actions);
        listCol.appendChild(row);
    });

    if (state.list.length===0) {
        listCol.appendChild(el('div', {class:'muted'}, 'Пока пусто'));
    }
}

function renderContentView() {
    const c = document.getElementById('contentCol');
    c.innerHTML = '';
    if (!state.selectedItem) {
        c.appendChild(el('div', {class:'muted'}, 'Выберите элемент слева или добавьте новый'));
        return;
    }

    const item = state.selectedItem;
    const table = el('table', {style:'width:100%; border-collapse: collapse;'}, []);
    const addRow = (left, right) => {
        const tr = el('tr', {}, [
            el('td', {style:'border:1px solid #ccc; padding:6px; width:40%;'}, left),
            el('td', {style:'border:1px solid #ccc; padding:6px;'}, right)
        ]);
        table.appendChild(tr);
    };

    if (state.menu==='deals') {
        addRow('id сделки', String(item.id));
        addRow('Наименование', item.name);
        addRow('Сумма', String(item.amount||0));

        if (item._resolved && item._resolved.contacts && item._resolved.contacts.length) {
            item._resolved.contacts.forEach(rc => addRow('id контакта: '+rc.id, rc.name));
        } else {
            addRow('Контакты', '—');
        }
    } else {
        addRow('id контакта', String(item.id));
        addRow('Имя', item.first_name);
        addRow('Фамилия', item.last_name||'');

        if (item._resolved && item._resolved.deals && item._resolved.deals.length) {
            item._resolved.deals.forEach(rd => addRow('id сделки: '+rd.id, rd.name));
        } else {
            addRow('Сделки', '—');
        }
    }

    // кнопки редактирования и удаления
    c.appendChild(table);
    c.appendChild(el('div', {style:'margin-top:8px;'}, [
        el('button', {class:'btn ghost', onclick: ()=> openEditForm(item.id)}, 'Редактировать'),
        el('button', {class:'btn danger', onclick: ()=> onDelete(item.id)}, 'Удалить')
    ]));
}

function renderContentForm(mode, preset={}) {
    const c = document.getElementById('contentCol');
    c.innerHTML = '';

    const form = el('div', {});
    if (state.menu==='deals') {

        const name = el('input', {type:'text', value: preset.name||''});
        const amount = el('input', {type:'number', value: (preset.amount ?? 0)});
        const contactsBox = renderMultiSelect(state.options.contacts, preset.contacts || (preset._resolved?.contacts?.map(x=>x.id) ?? []));

        form.append(
            field('Наименование *', name),
            field('Сумма', amount),
            field('Контакты (множ.)', contactsBox.container),
            el('div', {}, [
                el('button', {class:'btn primary', onclick: async ()=>{
                    const payload = {
                        name: name.value.trim(),
                        amount: parseInt(amount.value||'0',10)||0,
                        contacts: contactsBox.getSelected()
                    };
                    if (mode==='create') await createItem(payload);
                    else await updateItem(preset.id, payload);
                }}, mode==='create'?'Создать':'Сохранить'),
                el('button', {class:'btn ghost', style:'margin-left:6px;', onclick: ()=> cancelForm()}, 'Отмена')
            ])
        );
    } else {

        const first = el('input', {type:'text', value: preset.first_name||''});
        const last  = el('input', {type:'text', value: preset.last_name||''});
        const dealsBox = renderMultiSelect(state.options.deals, preset.deals || (preset._resolved?.deals?.map(x=>x.id) ?? []));

        form.append(
            field('Имя *', first),
            field('Фамилия', last),
            field('Сделки (множ.)', dealsBox.container),
            el('div', {}, [
                el('button', {class:'btn primary', onclick: async ()=>{
                    const payload = {
                        first_name: first.value.trim(),
                        last_name: last.value.trim(),
                        deals: dealsBox.getSelected()
                    };
                    if (mode==='create') await createItem(payload);
                    else await updateItem(preset.id, payload);
                }}, mode==='create'?'Создать':'Сохранить'),
                el('button', {class:'btn ghost', style:'margin-left:6px;', onclick: ()=> cancelForm()}, 'Отмена')
            ])
        );
    }

    c.appendChild(form);
}

function field(labelText, controlEl) {
    return el('div', {class:'field'}, [
        el('label', {}, labelText),
        controlEl
    ]);
}

function renderMultiSelect(options, selectedIds) {
    const sel = new Set((selectedIds||[]).map(Number));
    const wrap = el('div', {class:'chips'});
    options.forEach(opt => {
        const id = Number(opt.id);
        const chk = el('input', {type:'checkbox'});
        chk.checked = sel.has(id);
        chk.addEventListener('change', ()=> {
            if (chk.checked) sel.add(id); else sel.delete(id);
        });
        const chip = el('label', {class:'badge'}, [
            chk, ' ', opt.name
        ]);
        wrap.appendChild(chip);
    });
    return {
        container: wrap,
        getSelected: ()=> Array.from(sel)
    };
}

async function switchMenu(menu) {
    state.menu = menu;
    state.selectedId = null;
    state.selectedItem = null;
    await refreshListAndOptions();
    renderAll();
}

async function refreshListAndOptions() {
    const data = await api.get('init', {menu: state.menu});
    state.menu = data.menu;
    state.list = data.list;
    state.options = data.options;
}

async function selectItem(id) {
    state.selectedId = id;
    const det = await api.get('details', {menu: state.menu, id});
    state.selectedItem = det;
    renderContentView();
}

function openCreateForm() {
    state.selectedId = null;
    state.selectedItem = null;
    renderContentForm('create', {});
}

async function openEditForm(id) {
    if (!id && state.selectedId) id = state.selectedId;
    if (!id) return;
    const det = await api.get('details', {menu: state.menu, id});
    state.selectedId = id;
    state.selectedItem = det;
    renderContentForm('edit', det);
}

async function createItem(payload) {
    const created = await api.post('create', {menu: state.menu, payload});
    await refreshListAndOptions();
    renderList();
    await selectItem(created.id);
}

async function updateItem(id, payload) {
    const updated = await api.post('update', {menu: state.menu, id, payload});
    await refreshListAndOptions();
    renderList();
    await selectItem(updated.id);
}

async function onDelete(id) {
    const item = state.list.find(x=>x.id===id);
    const title = state.menu==='deals' ? (item?.name||'элемент') : ((item?.first_name||'')+' '+(item?.last_name||''));
    if (!confirm('Удалить "'+title.trim()+'"?')) return;
    await api.post('delete', {menu: state.menu, id});
    await refreshListAndOptions();
    state.selectedId = null;
    state.selectedItem = null;
    renderAll();
}

function cancelForm() {
    if (state.selectedId) renderContentView();
    else {
        const c = document.getElementById('contentCol');
        c.innerHTML = '<span class="muted">Выберите элемент слева или добавьте новый</span>';
    }
}

function renderAll() {
    renderMenu();
    renderList();
    if (state.selectedItem) renderContentView();
    else {
        const c = document.getElementById('contentCol');
        c.innerHTML = '<span class="muted">Выберите элемент слева или добавьте новый</span>';
    }
}

(async function init() {
    await refreshListAndOptions();
    renderAll();
    if (state.list.length) {
        await selectItem(state.list[0].id);
    }
})();
</script>
</body>
</html>
