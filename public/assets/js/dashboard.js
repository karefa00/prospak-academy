"use strict";
// Enhancement only: cards intentionally display the real server-rendered PDO data.
document.addEventListener('DOMContentLoaded',()=>{
 for(const card of document.querySelectorAll('.metric-card'))card.setAttribute('aria-label',card.textContent.trim().replace(/\s+/g,' '));
});