let currentPage = 1;

function toggleClass(e, toggleClassName) {
  if(e.className.includes(toggleClassName)) {
    e.className = e.className.replace(' ' + toggleClassName, '');
  } else {
    e.className += ' ' + toggleClassName;
  }
}

function movePage(e, page) {
  if (page == currentPage) {
    currentPage+=2;
    toggleClass(e, "left-side");
    toggleClass(e.nextElementSibling, "left-side");
    
  }
  else if (page = currentPage - 1) {
    currentPage-=2;
    toggleClass(e, "left-side");
    toggleClass(e.previousElementSibling, "left-side");
  }
  
}

let i=1;

function moveToLast() {
  i=currentPage;
  console.log("Current Page"+currentPage);
  movePageAndWait();
}

function movePageAndWait() {
    total=document.querySelectorAll('body > div.book > div').length-1;
    movePage(document.querySelector('body > div.book > div:nth-child('+i+')'), i);
    i+=2;
    if (i<total)
      setTimeout(movePageAndWait, 100);
}

