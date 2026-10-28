/* /shop/ catalog: collection + orientation chips, search, sort (uplinksync-shop-catalog.php).
 * Enqueued as a file so content filters (wpautop) can never inject paragraph tags into it (QA M2). */
(function(){
	var root=document.querySelector('.uls-shop');
	if(!root)return;
	// Collection chips carry data-filter; orientation chips carry data-orient.
	var collChips=[].slice.call(root.querySelectorAll('.chip[data-filter]'));
	var orientChips=[].slice.call(root.querySelectorAll('.chip-orient'));
	var sections=[].slice.call(root.querySelectorAll('.coll'));
	var sortSel=root.querySelector('.sortwrap select');
	var searchInput=root.querySelector('.uls-search');
	var noResults=root.querySelector('.uls-noresults');
	var clearBtn=root.querySelector('.uls-clear');
	// UPLAA-458 #4 — combined filter state: place + orientation + free-text.
	var state={coll:'all',orient:'all',q:''};

	function apply(){
		var q=state.q.trim().toLowerCase();
		var anyVisible=false;
		sections.forEach(function(sec){
			var collMatch=(state.coll==='all'||sec.dataset.coll===state.coll);
			var cards=[].slice.call(sec.querySelectorAll('.prod'));
			var shownInSec=0;
			cards.forEach(function(card){
				var ok=collMatch
					&&(state.orient==='all'||card.dataset.orient===state.orient)
					&&(q===''||(card.dataset.name||'').indexOf(q)!==-1);
				card.classList.toggle('is-hidden',!ok);
				if(ok){shownInSec++;}
			});
			var secVisible=shownInSec>0;
			sec.classList.toggle('is-hidden',!secVisible);
			if(secVisible){anyVisible=true;}
		});
		if(noResults){noResults.hidden=anyVisible;}
	}
	function setColl(f){
		state.coll=f;
		collChips.forEach(function(c){c.setAttribute('aria-pressed',c.dataset.filter===f?'true':'false');});
		apply();
	}
	function setOrient(o){
		state.orient=o;
		orientChips.forEach(function(c){c.setAttribute('aria-pressed',c.dataset.orient===o?'true':'false');});
		apply();
	}
	function applySort(mode){
		sections.forEach(function(sec){
			var grid=sec.querySelector('.pgrid');if(!grid)return;
			var cards=[].slice.call(grid.querySelectorAll('.prod'));
			cards.sort(function(a,b){
				if(mode==='price-asc')return (+a.dataset.price)-(+b.dataset.price)|| (+a.dataset.order)-(+b.dataset.order);
				if(mode==='price-desc')return (+b.dataset.price)-(+a.dataset.price)|| (+a.dataset.order)-(+b.dataset.order);
				if(mode==='name-asc')return a.dataset.name.localeCompare(b.dataset.name);
				return (+a.dataset.order)-(+b.dataset.order);
			});
			cards.forEach(function(c){grid.appendChild(c);});
		});
	}
	collChips.forEach(function(c){c.addEventListener('click',function(){setColl(c.dataset.filter);});});
	orientChips.forEach(function(c){c.addEventListener('click',function(){setOrient(c.dataset.orient);});});
	if(sortSel){sortSel.addEventListener('change',function(){applySort(sortSel.value);});}
	if(searchInput){searchInput.addEventListener('input',function(){state.q=searchInput.value;apply();});}
	if(clearBtn){clearBtn.addEventListener('click',function(){
		state.q='';if(searchInput){searchInput.value='';}
		setColl('all');setOrient('all');
		if(searchInput){searchInput.focus();}
	});}
})();
