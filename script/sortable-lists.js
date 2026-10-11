/* exported makeSortableLists, sortedListIds, postSortedLists */
/* global Sortable */

var sortableGroupCount = 0;

/**
 * Lets items be dragged within and between the given lists.
 * Options: draggable (item selector, default "li"), filter (selector of items
 * or controls that do not start a drag) and onChange(evt), called after an item
 * lands in a new position.
 */
function makeSortableLists(lists, options) {
  options = options || {};
  var group = "sortable" + (++sortableGroupCount);
  Array.prototype.forEach.call(lists, function (list) {
    Sortable.create(list, {
      group: group,
      draggable: options.draggable || "li",
      filter: options.filter || "",
      preventOnFilter: false,
      revertOnSpill: true,
      animation: 150,
      delay: 200,
      delayOnTouchOnly: true,
      ghostClass: "sortable-ghost",
      chosenClass: "sortable-chosen",
      onEnd: function (evt) {
        if (options.onChange && (evt.from !== evt.to || evt.oldIndex !== evt.newIndex)) {
          options.onChange(evt);
        }
      },
    });
  });
}

/**
 * Returns the numeric ids of the list's items, with the given id prefix removed.
 */
function sortedListIds(list, prefix) {
  var ids = [];
  Array.prototype.forEach.call(list.children, function (item) {
    if (item.id.indexOf(prefix) === 0) {
      var id = item.id.substring(prefix.length);
      if (/^\d+$/.test(id)) {
        ids.push(id);
      }
    }
  });
  return ids;
}

/**
 * POSTs body to url and shows the response in statusEl.
 * Options: html (the response is escaped HTML) and onSuccess().
 */
function postSortedLists(url, body, statusEl, options) {
  options = options || {};
  statusEl.className = "sortable-saving";
  statusEl.textContent = "";
  var request = new XMLHttpRequest();
  request.open("POST", url);
  request.onload = function () {
    var ok = request.status >= 200 && request.status < 300;
    statusEl.className = ok ? "highlight" : "attention";
    if (options.html) {
      statusEl.innerHTML = request.responseText;
    } else {
      statusEl.textContent = request.responseText;
    }
    if (ok && options.onSuccess) {
      options.onSuccess();
    }
  };
  request.onerror = function () {
    statusEl.className = "attention";
    statusEl.textContent = request.statusText;
  };
  request.send(body);
}
