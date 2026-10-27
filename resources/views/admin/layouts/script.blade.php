<!-- FLOATING TOAST CONTAINER -->
<div id="toast-wrapper" class="fixed bottom-4 right-4 z-50 pointer-events-none space-y-2"></div>

<script>
  // Mobile sidebar
  function toggleSidebar(open) {
    document.getElementById('app-sidebar').classList.toggle('-translate-x-full', !open);
    document.getElementById('sidebar-backdrop').classList.toggle('hidden', !open);
  }

  // Dropdown menus (bell, user menu): click a button to toggle, click outside to close
  function toggleMenu(id, e) {
    document.querySelectorAll('[data-menu]').forEach(function (m) {
      if (m.id === id) m.classList.toggle('hidden'); else m.classList.add('hidden');
    });
    e.stopPropagation();
  }
  document.addEventListener('click', function (e) {
    document.querySelectorAll('[data-menu]').forEach(function (m) {
      if (!m.contains(e.target)) m.classList.add('hidden');
    });
  });

  // Ask before delete: <form data-confirm="Are you sure?">
  document.addEventListener('submit', function (e) {
    if (e.target.dataset.confirm && !confirm(e.target.dataset.confirm)) e.preventDefault();
  });

  // Small toast message
  function showToast(message, type) {
    var wrapper = document.getElementById('toast-wrapper');
    var toast = document.createElement('div');
    var ok = type === 'success';
    toast.className = 'bg-slate-900 text-white px-4 py-2.5 rounded-lg shadow-xl border-l-4 flex items-center gap-2.5 text-xs ' + (ok ? 'border-emerald-500' : 'border-blue-500');
    toast.innerHTML = '<i class="fa-solid ' + (ok ? 'fa-circle-check text-emerald-400' : 'fa-circle-info text-sky-400') + '"></i><span></span>';
    toast.querySelector('span').textContent = message;
    wrapper.appendChild(toast);
    setTimeout(function () { toast.remove(); }, 3200);
  }

  // Short forms (EC, CTE, BG, CWP ...): show the full form on hover, everywhere in the page.
  // The list lives in config/glossary.php.
  @php
    $shortForms = [];
    foreach (config('glossary') as $group) {
        foreach ($group as $short => [$full, $meaning, $tooltip]) {
            if ($tooltip) { $shortForms[$short] = $full . ($meaning ? ' – ' . $meaning : ''); }
        }
    }
  @endphp
  (function () {
    var forms = @json($shortForms);
    var keys = Object.keys(forms).sort(function (a, b) { return b.length - a.length; })
      .map(function (k) { return k.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); });
    if (!keys.length) return;
    // Whole word only, and never inside codes like MN-ONHP-2018/1
    var pattern = new RegExp('(?<![A-Za-z0-9-])(' + keys.join('|') + ')(?![A-Za-z0-9-])', 'g');
    var skip = { SCRIPT: 1, STYLE: 1, TEXTAREA: 1, OPTION: 1, SELECT: 1, INPUT: 1, ABBR: 1, BUTTON: 0 };

    function wrap(root) {
      var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
        acceptNode: function (node) {
          for (var el = node.parentNode; el && el !== root; el = el.parentNode) {
            if (skip[el.nodeName] || (el.nodeName === 'text') || el.isContentEditable) return NodeFilter.FILTER_REJECT;
          }
          pattern.lastIndex = 0;
          return pattern.test(node.nodeValue) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
        }
      });
      var nodes = [];
      while (walker.nextNode()) nodes.push(walker.currentNode);

      nodes.forEach(function (node) {
        var frag = document.createDocumentFragment(), text = node.nodeValue, last = 0, m;
        pattern.lastIndex = 0;
        while ((m = pattern.exec(text))) {
          frag.appendChild(document.createTextNode(text.slice(last, m.index)));
          var abbr = document.createElement('abbr');
          abbr.title = forms[m[1]];
          abbr.textContent = m[1];
          abbr.className = 'short-form';
          frag.appendChild(abbr);
          last = m.index + m[1].length;
        }
        frag.appendChild(document.createTextNode(text.slice(last)));
        node.parentNode.replaceChild(frag, node);
      });
    }

    document.addEventListener('DOMContentLoaded', function () {
      var main = document.querySelector('main');
      if (main) wrap(main);
    });
  })();
</script>
