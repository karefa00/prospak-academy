"use strict";
document.addEventListener('DOMContentLoaded', () => {
 const sidebar=document.querySelector('#portalSidebar');
 const toggle=document.querySelector('[data-toggle-sidebar]');
 if(sidebar && toggle){
  const backdrop=document.createElement('div');backdrop.className='nav-backdrop';document.body.appendChild(backdrop);
  const close=()=>{sidebar.classList.remove('sidebar-open');backdrop.classList.remove('active');};
  toggle.addEventListener('click',()=>{sidebar.classList.toggle('sidebar-open');backdrop.classList.toggle('active');});
  backdrop.addEventListener('click',close);document.addEventListener('keydown',e=>{if(e.key==='Escape')close();});
 }
 document.querySelectorAll('[data-toggle-password]').forEach(button=>button.addEventListener('click',()=>{
  const control=document.getElementById(button.getAttribute('data-toggle-password'));
  if(!control)return;const visible=control.type==='password';control.type=visible?'text':'password';button.textContent=visible?'Hide':'Show';
 }));
 const department=document.querySelector('[data-department]');const programme=document.querySelector('[data-programme]');
 if(department&&programme){
   const sync=()=>{for(const option of programme.options){if(!option.value)continue;option.hidden=option.dataset.departmentId!==department.value;}
    for(const option of programme.options){if(option.hidden && option.selected)option.selected=false;}};department.addEventListener('change',sync);sync();
 }
 const staff=document.querySelector('[data-staff-role]');
 if(staff){const sync=()=>document.querySelectorAll('[data-lecturer-only]').forEach(el=>{const show=staff.value==='lecturer';el.hidden=!show;el.querySelectorAll('input,select').forEach(input=>{input.required=show;input.disabled=!show;});});staff.addEventListener('change',sync);sync();}
});