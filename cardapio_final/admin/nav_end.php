</div><!-- /.admin-layout -->
<script>
function openSidebar(){
  document.getElementById('sidebar').classList.add('open');
  document.getElementById('sidebarOverlay').classList.add('open');
}
function closeSidebar(){
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('open');
}
// Dark toggle (sidebar + mobile)
(function(){
  const root = document.documentElement;
  const isDark = () => root.getAttribute('data-theme') !== 'light';
  function applyBtn(btn){
    if(!btn) return;
    btn.textContent = isDark() ? '☀️' : '🌙';
    btn.onclick = () => {
      const dark = isDark();
      root.setAttribute('data-theme', dark ? 'light' : 'dark');
      localStorage.setItem('darkMode', dark ? '0' : '1');
      document.querySelectorAll('#darkToggle,#darkToggleMobile').forEach(b => b.textContent = dark ? '🌙' : '☀️');
    };
  }
  applyBtn(document.getElementById('darkToggle'));
  applyBtn(document.getElementById('darkToggleMobile'));
})();
</script>
