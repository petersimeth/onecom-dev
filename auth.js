// Shared behaviour for the auth pages (login, register, reset, profile).
// Loaded with `defer`, so the DOM is ready when this runs.
(function () {
  "use strict";

  // --- Show / hide password toggles -----------------------------------------
  document.querySelectorAll(".password-toggle").forEach(function (button) {
    button.addEventListener("click", function () {
      var input = document.getElementById(button.dataset.target);
      if (!input) return;
      var show = input.type === "password";
      input.type = show ? "text" : "password";
      button.textContent = show ? "Hide" : "Show";
      button.setAttribute("aria-label", show ? "Hide password" : "Show password");
    });
  });

  // --- Password strength meter ----------------------------------------------
  // Any <input data-strength> gets a live meter inserted right after its field.
  var LABELS = ["Too short", "Weak", "Fair", "Good", "Strong"];

  function scorePassword(value) {
    if (!value || value.length < 8) return 0;
    var score = 0;
    if (value.length >= 8) score++;
    if (value.length >= 12) score++;
    var variety = 0;
    if (/[a-z]/.test(value)) variety++;
    if (/[A-Z]/.test(value)) variety++;
    if (/[0-9]/.test(value)) variety++;
    if (/[^A-Za-z0-9]/.test(value)) variety++;
    if (variety >= 2) score++;
    if (variety >= 3) score++;
    // Penalise obvious repetition / sequences.
    if (/^(.)\1+$/.test(value) || /^(?:0123|1234|2345|abcd|qwer|password)/i.test(value)) {
      score = Math.min(score, 1);
    }
    return Math.max(0, Math.min(4, score));
  }

  document.querySelectorAll("input[data-strength]").forEach(function (input) {
    var meter = document.createElement("div");
    meter.className = "pw-strength";
    var track = document.createElement("span");
    track.className = "pw-strength-track";
    var bar = document.createElement("span");
    bar.className = "pw-strength-bar";
    track.appendChild(bar);
    var label = document.createElement("span");
    label.className = "pw-strength-label";
    meter.appendChild(track);
    meter.appendChild(label);

    // Place the meter just after the field (or its wrapping .password-field).
    var anchor = input.closest(".password-field") || input;
    anchor.parentNode.insertBefore(meter, anchor.nextSibling);

    function update() {
      var value = input.value || "";
      if (value === "") {
        meter.removeAttribute("data-score");
        bar.style.width = "0%";
        label.textContent = "";
        return;
      }
      var score = scorePassword(value);
      meter.setAttribute("data-score", String(score));
      bar.style.width = (score / 4) * 100 + "%";
      label.textContent = LABELS[score];
    }

    input.addEventListener("input", update);
  });
})();
