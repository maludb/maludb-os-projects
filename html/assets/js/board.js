/* The board's drag-and-drop (docs/build-specs/board.md): an enhancement over each card's "Move to…" select.
 * A drop onto another column POSTs issue_transition to the first status of that column reachable from the
 * card's status; none reachable → the card springs back and the region re-fetches. A drop within a column
 * posts nothing (columns are not ranked). While a drag is in progress the body carries data-dragging, and
 * the 30 s poll waits for it to end (the region's hx-trigger reads that attribute). Nothing here runs
 * without SortableJS; the selects stand alone. */
(function () {
  function post(url, values) {
    if (!window.htmx) return;
    htmx.ajax('POST', url, { target: '#flash', swap: 'innerHTML', values: values });
  }
  function refresh(container) {
    var region = container.closest('#board-columns');
    if (region && window.htmx) { htmx.trigger(region, 'boardChanged'); }
  }
  window.bindBoard = function () {
    if (!window.Sortable) return;
    document.querySelectorAll('[data-board-droppable]').forEach(function (col) {
      if (col.__boardSortable) return;
      col.__boardSortable = Sortable.create(col, {
        group: 'board',
        animation: 120,
        draggable: '.board-card',
        filter: 'select, option, a',
        preventOnFilter: false,
        onStart: function () { document.body.setAttribute('data-dragging', '1'); },
        onEnd: function (evt) {
          document.body.removeAttribute('data-dragging');
          var card = evt.item, to = evt.to, from = evt.from;
          if (!to || to === from) return;                                   // within a column: nothing to post
          var reachable = (card.dataset.canMoveTo || '').split(',').filter(Boolean);
          var statuses = (to.dataset.statuses || '').split(',').filter(Boolean);
          var target = null;
          for (var i = 0; i < statuses.length; i++) { if (reachable.indexOf(statuses[i]) !== -1) { target = statuses[i]; break; } }
          if (target === null) {
            from.appendChild(card);                                         // spring back
            var f = document.getElementById('flash');
            if (f) { f.innerHTML = '<div class="alert alert-warning m-3">The workflow does not allow that move.</div>'; }
            refresh(from);
            return;
          }
          post('/issues/transition.php', { issue: card.dataset.id, status: target, from_column: from.dataset.columnName || '', to_column: to.dataset.columnName || '' });
        }
      });
    });
  };
  if (window.__boardListeners) return;                                      // the file is re-run after every swap of the board page
  window.__boardListeners = true;
  document.addEventListener('DOMContentLoaded', window.bindBoard);
  window.addEventListener('load', window.bindBoard);                         // SortableJS is loaded after the page's content
  // htmx:load fires on every element htmx adds — an outerHTML swap of the region included, whose afterSwap
  // fires on the detached old element and never reaches the body.
  document.body.addEventListener('htmx:load', function () { if (window.bindBoard) { window.bindBoard(); } });
  document.body.addEventListener('htmx:afterSwap', function () { if (window.bindBoard) { window.bindBoard(); } });
  document.body.addEventListener('htmx:afterRequest', function (e) {
    // a refused drop (422) — the region re-fetches on the trigger the handler did not send; ask for it
    var xhr = e.detail && e.detail.xhr;
    if (xhr && xhr.status === 422 && e.detail.pathInfo && /\/issues\/transition\.php/.test(e.detail.pathInfo.requestPath || '')) {
      var region = document.getElementById('board-columns');
      if (region && window.htmx) { htmx.trigger(region, 'boardChanged'); }
    }
  });
})();
