function serializeList(ul) {
  const arr = [];
  ul.querySelectorAll(":scope > li").forEach((li) => {
    const id = li.dataset.id;
    const childrenUl = li.querySelector(":scope > ul");
    arr.push({
      id: id,
      children: childrenUl ? serializeList(childrenUl) : [],
    });
  });
  return arr;
}

function updateOutput(el) {
  const output = el.closest(".nestable").querySelector(".nestable-output");
  if (output) {
    output.value = JSON.stringify(serializeList(el));
  }
}

function checkMaxDepth(list, maxDepth = 3) {
  let tooDeep = false;
  function walk(ul, depth = 1) {
    if (depth > maxDepth) {
      tooDeep = true;
      return;
    }
    ul.querySelectorAll(":scope > li > ul").forEach((childUl) => walk(childUl, depth + 1));
  }
  walk(list);
  return tooDeep;
}
async function saveOrder(groupId, orderData) {
  const loader = document.getElementById("loader");
  if (loader) loader.style.display = "block";

  try {
    const { ok, data } = await apiFetch(baseUrl + "saveOrder/" + groupId, {
      method: "POST",
      body: new URLSearchParams({
        order: orderData,
      }),
    });

    if (ok && data.success) {
      showAlert(data.message || "Berhasil menyimpan order", data.type || "success", 5000, "Berhasil!");
    } else {
      showAlert(data.message || "Gagal menyimpan order", "danger", false, "Oops!");
    }
  } catch (err) {
    showAlert("Terjadi kesalahan koneksi", "danger", false, "Error!");
  } finally {
    if (loader) loader.style.display = "none";
  }
}

document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll(".dd.nestable").forEach((vthis) => {
    const groupId = vthis.dataset.groupId;
    const rootUl = vthis.querySelector("ul.list-group");

    function initNestedSortable(ul) {
      Sortable.create(ul, {
        group: "nested-" + groupId,
        handle: ".handle",
        animation: 150,
        fallbackOnBody: true,
        swapThreshold: 0.65,
        ghostClass: "sortable-placeholder",
        onEnd: function () {
          const alertEl = document.getElementById("alert-depth");
          if (checkMaxDepth(rootUl, maxNestDepth)) {
            if (alertEl) {
              alertEl.textContent = `⚠️ Group ${groupId} terlalu dalam! Maksimal ${maxNestDepth} level.`;
              alertEl.style.display = "block";
            }
          } else if (alertEl) {
            alertEl.style.display = "none";
          }
          updateOutput(rootUl);

          const outputVal = vthis.querySelector(".nestable-output").value;
          saveOrder(groupId, outputVal);
        },
      });

      ul.querySelectorAll(":scope > li > ul").forEach(initNestedSortable);
    }

    if (rootUl) {
      initNestedSortable(rootUl);
      updateOutput(rootUl); // initial output
    }
  });
});
