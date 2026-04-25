/* LibraryMS — app.js */

document.addEventListener('DOMContentLoaded', () => {

  // ── Sidebar toggle (mobile) ──────────────────────────────
  const sidebar  = document.getElementById('sidebar');
  const overlay  = document.getElementById('sidebarOverlay');
  const toggler  = document.querySelector('.sidebar-toggle');
  const closeBtn = document.getElementById('sidebarClose');

  function openSidebar()  { sidebar?.classList.add('open'); overlay?.classList.add('show'); }
  function closeSidebar() { sidebar?.classList.remove('open'); overlay?.classList.remove('show'); }

  toggler?.addEventListener('click', openSidebar);
  closeBtn?.addEventListener('click', closeSidebar);
  overlay?.addEventListener('click', closeSidebar);

  // ── DataTables (auto-init any .dt-table) ─────────────────
  document.querySelectorAll('.dt-table').forEach(el => {
    $(el).DataTable({
      responsive: true,
      pageLength: 15,
      language: { search: '', searchPlaceholder: 'Search…', emptyTable: 'No records found' },
      dom: "<'row align-items-center mb-2'<'col-sm-6'l><'col-sm-6 text-end'f>>" +
           "<'row'<'col-12'tr>>" +
           "<'row align-items-center mt-2'<'col-sm-6 text-muted small'i><'col-sm-6 d-flex justify-content-end'p>>",
    });
  });

  // ── Auto-dismiss alerts ──────────────────────────────────
  setTimeout(() => {
    document.querySelectorAll('.alert.auto-dismiss').forEach(el => {
      new bootstrap.Alert(el).close();
    });
  }, 4000);

  // ── Confirm delete ────────────────────────────────────────
  document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', e => {
      if (!confirm('Are you sure you want to delete this record? This cannot be undone.')) e.preventDefault();
    });
  });

  // ── Print page ────────────────────────────────────────────
  document.querySelectorAll('.btn-print').forEach(b => b.addEventListener('click', () => window.print()));

});
