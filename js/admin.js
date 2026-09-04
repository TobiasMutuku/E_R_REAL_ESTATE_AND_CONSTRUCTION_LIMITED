(function () {
  "use strict";

  var defaultAdmins = [
    {
      id: 1,
      name: "Elijah Wambua",
      email: "admin@errealestate.co.ke",
      role: "CEO & Director",
      lastActive: "Just now",
      status: "Active",
      initials: "AM",
    },
    {
      id: 2,
      name: "Grace Wanjiku",
      email: "grace@errealestate.co.ke",
      role: "Administrator",
      lastActive: "Today, 09:42",
      status: "Active",
      initials: "GW",
    },
    {
      id: 3,
      name: "David Mwangi",
      email: "david@errealestate.co.ke",
      role: "Editor",
      lastActive: "Yesterday, 16:18",
      status: "Pending",
      initials: "DM",
    },
    {
      id: 4,
      name: "Tobias Mutuku",
      email: "Tobias@errealestate.co.ke",
      role: "Administrator",
      lastActive: "Invitation ready",
      status: "Pending",
      initials: "TM",
    },
  ];
  var adminKey = "er-admins";
  var settingsKey = "er-admin-settings";
  var admins = load(adminKey, defaultAdmins);
  var owner = admins.find(function (admin) {
    return admin.email.toLowerCase() === "admin@errealestate.co.ke";
  });
  if (owner) {
    owner.name = "Elijah Wambua";
    owner.role = "CEO & Director";
    owner.initials = "EW";
    save(adminKey, admins);
  }
  if (
    !admins.some(function (admin) {
      return admin.email.toLowerCase() === "tobias@errealestate.co.ke";
    })
  ) {
    admins.push(defaultAdmins[3]);
    save(adminKey, admins);
  }
  var views = document.querySelectorAll(".view");
  var navLinks = document.querySelectorAll(".sidebar-link[data-view]");
  var modal = document.getElementById("adminModal");
  var toast = document.getElementById("toast");
  var toastTimer;

  function load(key, fallback) {
    try {
      var saved = localStorage.getItem(key);
      return saved
        ? JSON.parse(saved)
        : Array.isArray(fallback)
          ? fallback.slice()
          : fallback;
    } catch (error) {
      return Array.isArray(fallback) ? fallback.slice() : fallback;
    }
  }

  function save(key, value) {
    try {
      localStorage.setItem(key, JSON.stringify(value));
    } catch (error) {}
  }

  function showToast(message) {
    toast.querySelector("span").textContent = message;
    toast.classList.add("show");
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(function () {
      toast.classList.remove("show");
    }, 2800);
  }

  function switchView(viewName) {
    views.forEach(function (view) {
      view.classList.toggle("active", view.dataset.page === viewName);
    });
    navLinks.forEach(function (link) {
      link.classList.toggle("active", link.dataset.view === viewName);
    });
    document.getElementById("breadcrumbCurrent").textContent =
      viewName === "admins"
        ? "Admin access"
        : viewName === "settings"
          ? "Site settings"
          : "Overview";
    document.getElementById("adminSidebar").classList.remove("open");
    window.scrollTo(0, 0);
  }

  function initials(name) {
    return name
      .split(" ")
      .map(function (part) {
        return part.charAt(0);
      })
      .slice(0, 2)
      .join("")
      .toUpperCase();
  }

  function renderAdmins(filter) {
    var query = (filter || "").toLowerCase();
    var visibleAdmins = admins.filter(function (admin) {
      return (
        (admin.name + admin.email + admin.role).toLowerCase().indexOf(query) !==
        -1
      );
    });
    var body = document.getElementById("adminTableBody");
    body.innerHTML = visibleAdmins.length
      ? visibleAdmins
          .map(function (admin) {
            return (
              '<tr><td><div class="person"><span class="person-avatar">' +
              admin.initials +
              "</span><span>" +
              admin.name +
              "<small>" +
              admin.email +
              '</small></span></div></td><td><span class="role-tag">' +
              admin.role +
              "</span></td><td>" +
              admin.lastActive +
              '</td><td><span class="status-tag' +
              (admin.status === "Pending" ? " pending" : "") +
              '">' +
              admin.status +
              '</span></td><td class="row-actions"><button class="more-button" type="button" aria-label="Actions for ' +
              admin.name +
              '"><i class="fas fa-ellipsis-h"></i></button><div class="action-menu"><button type="button" data-action="reset">Reset access</button><button class="delete" type="button" data-action="remove" data-id="' +
              admin.id +
              '">Remove</button></div></td></tr>'
            );
          })
          .join("")
      : '<tr><td colspan="5" class="empty-state">No administrators match your search.</td></tr>';
    document.getElementById("adminTableMeta").textContent =
      visibleAdmins.length +
      " administrator" +
      (visibleAdmins.length === 1 ? "" : "s");
    document.getElementById("adminCount").textContent = admins.length;
    document.getElementById("activeAdminStat").textContent = admins.filter(
      function (admin) {
        return admin.status === "Active";
      },
    ).length;
  }

  function openModal() {
    modal.classList.add("open");
    modal.setAttribute("aria-hidden", "false");
    modal.querySelector("input").focus();
  }
  function closeModal() {
    modal.classList.remove("open");
    modal.setAttribute("aria-hidden", "true");
    document.getElementById("adminForm").reset();
  }

  navLinks.forEach(function (link) {
    link.addEventListener("click", function () {
      switchView(link.dataset.view);
    });
  });
  document.querySelectorAll("[data-view-target]").forEach(function (button) {
    button.addEventListener("click", function () {
      switchView(button.dataset.viewTarget);
    });
  });
  document.querySelectorAll("[data-open-modal]").forEach(function (button) {
    button.addEventListener("click", openModal);
  });
  document.querySelectorAll("[data-close-modal]").forEach(function (button) {
    button.addEventListener("click", closeModal);
  });
  document.getElementById("menuButton").addEventListener("click", function () {
    document.getElementById("adminSidebar").classList.toggle("open");
  });
  document
    .getElementById("adminSearch")
    .addEventListener("input", function (event) {
      renderAdmins(event.target.value);
    });

  document
    .getElementById("adminForm")
    .addEventListener("submit", function (event) {
      event.preventDefault();
      var formData = new FormData(event.target);
      var name = formData.get("name");
      admins.unshift({
        id: Date.now(),
        name: name,
        email: formData.get("email"),
        role: formData.get("role"),
        lastActive: "Invitation sent",
        status: "Pending",
        initials: initials(name),
      });
      save(adminKey, admins);
      renderAdmins();
      closeModal();
      showToast("Invitation sent to " + name);
    });

  document
    .getElementById("adminTableBody")
    .addEventListener("click", function (event) {
      var moreButton = event.target.closest(".more-button");
      if (moreButton) {
        document.querySelectorAll(".action-menu.open").forEach(function (menu) {
          if (menu !== moreButton.nextElementSibling)
            menu.classList.remove("open");
        });
        moreButton.nextElementSibling.classList.toggle("open");
        return;
      }
      var removeButton = event.target.closest('[data-action="remove"]');
      if (
        removeButton &&
        window.confirm("Remove this administrator's access?")
      ) {
        admins = admins.filter(function (admin) {
          return String(admin.id) !== removeButton.dataset.id;
        });
        save(adminKey, admins);
        renderAdmins(document.getElementById("adminSearch").value);
        showToast("Administrator access removed");
      }
      var resetButton = event.target.closest('[data-action="reset"]');
      if (resetButton) showToast("Access reset link prepared");
    });

  document.addEventListener("click", function (event) {
    if (!event.target.closest(".row-actions"))
      document.querySelectorAll(".action-menu.open").forEach(function (menu) {
        menu.classList.remove("open");
      });
  });
  modal.addEventListener("click", function (event) {
    if (event.target === modal) closeModal();
  });
  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
      closeModal();
      document.getElementById("adminSidebar").classList.remove("open");
    }
  });

  var savedSettings = load(settingsKey, null);
  if (savedSettings)
    Object.keys(savedSettings).forEach(function (key) {
      var field = document.querySelector('[name="' + key + '"]');
      if (field)
        field.type === "checkbox"
          ? (field.checked = savedSettings[key])
          : (field.value = savedSettings[key]);
    });
  document
    .getElementById("settingsForm")
    .addEventListener("submit", function (event) {
      event.preventDefault();
      var values = {};
      new FormData(event.target).forEach(function (value, key) {
        values[key] = value;
      });
      event.target
        .querySelectorAll('input[type="checkbox"]')
        .forEach(function (field) {
          values[field.name] = field.checked;
        });
      save(settingsKey, values);
      document.getElementById("savedState").innerHTML =
        '<i class="fas fa-check-circle"></i> All changes saved';
      showToast("Site settings saved");
    });

  document
    .getElementById("signOutButton")
    .addEventListener("click", function () {
      window.location.href = "login.html";
    });
  document
    .getElementById("viewAllActivity")
    .addEventListener("click", function () {
      showToast("Activity history is up to date");
    });
  renderAdmins();
})();
