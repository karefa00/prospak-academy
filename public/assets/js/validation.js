"use strict";
document.addEventListener('DOMContentLoaded',()=>{
 document.querySelectorAll('form[data-validate]').forEach(form=>form.addEventListener('submit',event=>{
  const p=form.querySelector('[name="new_password"]');const c=form.querySelector('[name="confirm_password"]');
  if(p&&c&&p.value!==c.value){event.preventDefault();c.setCustomValidity('Passwords must match');c.reportValidity();}
 }));
 document.querySelectorAll('[name="confirm_password"]').forEach(field=>field.addEventListener('input',()=>field.setCustomValidity('')));
});