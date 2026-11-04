(function () {
  'use strict';
  const root = document.getElementById('people-form');
  if (!root) return;
  const role = root.querySelector('[name="role"]');
  if (!role) return;
  function update() {
    const value = role.value;
    root.querySelectorAll('[data-for-role]').forEach(function (section) {
      const key = section.getAttribute('data-for-role');
      const visible = key === 'basic' ? !['student','lecturer'].includes(value) :
        key === 'person' ? ['student','lecturer'].includes(value) : key === value;
      section.hidden = !visible;
      section.querySelectorAll('[data-required-for]').forEach(function (input) {
        input.required = visible;
      });
    });
  }
  role.addEventListener('change', update);
  update();
}());
