// Alpine factory untuk combobox live-search generik — dipakai di beberapa
// halaman (org-chart Builder, Task/Project). File statis biasa (bukan lewat
// Vite/npm), supaya tidak perlu proses build untuk dimuat.
function searchPicker(items) {
    return {
        items: items,
        search: '',
        open: false,
        labelFor(id) {
            const found = this.items.find(i => String(i.id) === String(id));
            return found ? found.name : '';
        },
        filtered() {
            const q = this.search.trim().toLowerCase();
            const list = q ? this.items.filter(i => i.name.toLowerCase().includes(q)) : this.items;
            return list.slice(0, 50);
        },
    };
}
