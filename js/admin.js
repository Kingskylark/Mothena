// ========== js/admin.js - Admin JavaScript ==========
// Admin panel JavaScript functionality
document.addEventListener("DOMContentLoaded", function () {
  // Auto-hide alerts
  const alerts = document.querySelectorAll(".alert");
  alerts.forEach(function (alert) {
    setTimeout(function () {
      alert.style.opacity = "0";
      setTimeout(function () {
        alert.remove();
      }, 300);
    }, 5000);
  });

  // Confirm delete actions
  const deleteButtons = document.querySelectorAll(".btn-delete");
  deleteButtons.forEach(function (btn) {
    btn.addEventListener("click", function (e) {
      if (!confirm("Are you sure you want to delete this item?")) {
        e.preventDefault();
      }
    });
  });

  // Search functionality
  const searchInput = document.querySelector("#searchInput");
  if (searchInput) {
    let searchTimeout;
    searchInput.addEventListener("input", function () {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(function () {
        const searchTerm = searchInput.value;
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set("search", searchTerm);
        currentUrl.searchParams.set("page", "1");
        window.location.href = currentUrl.toString();
      }, 500);
    });
  }

  // Table row selection
  const tableRows = document.querySelectorAll("tbody tr");
  tableRows.forEach(function (row) {
    row.addEventListener("click", function (e) {
      if (e.target.tagName !== "A" && e.target.tagName !== "BUTTON") {
        row.classList.toggle("table-active");
      }
    });
  });

  // Chart initialization (if Chart.js is loaded)
  if (typeof Chart !== "undefined") {
    initializeCharts();
  }
});

function initializeCharts() {
  // Users by trimester chart
  const trimesterCanvas = document.getElementById("trimesterChart");
  if (trimesterCanvas) {
    const ctx = trimesterCanvas.getContext("2d");
    new Chart(ctx, {
      type: "doughnut",
      data: {
        labels: ["First Trimester", "Second Trimester", "Third Trimester"],
        datasets: [
          {
            data: [30, 45, 25], // Replace with actual data
            backgroundColor: ["#ff6b9d", "#4ecdc4", "#45b7d1"],
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
      },
    });
  }
}
