
document.addEventListener('DOMContentLoaded',()=>{
document.querySelectorAll('.r9ws-carousel').forEach(c=>{
let cards=[...c.children];if(cards.length<2)return;
let i=0;cards.forEach((e,n)=>e.style.display=n?'none':'');
setInterval(()=>{cards[i].style.display='none';i=(i+1)%cards.length;cards[i].style.display='';},6000);
});
});
