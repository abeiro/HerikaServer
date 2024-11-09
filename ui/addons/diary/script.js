let currentPage = 1;

function toggleClass(e, toggleClassName) {
  if(e.className.includes(toggleClassName)) {
    e.className = e.className.replace(' ' + toggleClassName, '');
  } else {
    e.className += ' ' + toggleClassName;
  }
}

function movePage(e, page) {
  if (page >= currentPage) {
    currentPage+=2;
    toggleClass(e, "left-side");
    toggleClass(e.nextElementSibling, "left-side");
    
  }
  else if (page < currentPage && e.className.includes("left-side")) {
    currentPage-=2;
	toggleClass(e, "left-side");
	toggleClass(e.previousElementSibling, "left-side");
  }
  
}

function moveToLast() {
  console.log("Current Page"+currentPage);
  movePageAndWait();
}

function movePageAndWait() {
	const total=document.querySelectorAll('body > div.book > div').length-1;
	if (currentPage<total) {
		movePage(document.querySelector('body > div.book > div:nth-child('+currentPage+')'), currentPage);
	}
	if (currentPage<total) {
		setTimeout(movePageAndWait, 100);
	}
}

