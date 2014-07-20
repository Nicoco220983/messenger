function Messenger(iId) {
	
	this.serverEntry = "server/messenger.php";
	
	this.defaultCriteria = {act:'get'};
	
	this.messageHtmlTemplate = '<div class="message"><table><tr><td class="icon"></td><td><table><tr><td class="author"></td><td class="date"></td></tr></table><div class="txt"></div></td></tr></table></div>';
	
	this.debug = true;
	
	this.init = function(iId) {
		var aElem;
		if(iId) aElem = document.getElementById(iId);
		if(!aElem) {
			var aBodys = document.getElementsByTagName('body');
			if(aBodys.length>0) aElem = aBodys[0];
		}
		if(aElem) {
			this.html = aElem;
			this.retrieveMessages(this.defaultCriteria);
		}
	};
	
	this.send = function(iRequest, iSuccessFunc) {
		var xhr = (typeof XMLHttpRequest != 'undefined') ? new XMLHttpRequest() : new ActiveXObject('Microsoft.XMLHTTP');
		xhr.open('POST', this.serverEntry, false);
		xhr.setRequestHeader('Content-Type', 'application/json');
		xhr.messenger = this;
		xhr.onreadystatechange = function() {
			// http://xhr.spec.whatwg.org/#dom-xmlhttprequest-readystate
			if (xhr.readyState == 4) { // DONE
				var oStatus = xhr.status;
				if (oStatus == 200) {
				  // parse reply with JSON
					var oReply = xhr.responseText;
					try {
					  oReply = JSON.parse(xhr.responseText);
					} catch (e) {
						console.error(oReply);
						return;
					}
					if(this.messenger.debug) { console.log('PHP reply:'); console.log(oReply); }
					if (oReply!==null && oReply.error!==undefined) {
						console.error(oReply.error);
					} else {
						iSuccessFunc && iSuccessFunc.call(this.messenger, oReply);
					}
				} else {
					console.error("XHR ERROR status: " + oStatus);
				}
			}
		};
		if(this.debug) { console.log('PHP send:'); console.log(iRequest); }
		xhr.send(JSON.stringify(iRequest));
	};
	
	this.retrieveMessages = function(iCriteria) {
		this.send(iCriteria, function(oReply) {
		  this.loadMessages(oReply);
		});
	};
	
	this.loadMessages = function(iMessages) {
		if(!this.html) return;
		for(var m in iMessages) {
			var aMessage = iMessages[m];
			if(!this.tempDiv) this.tempDiv = document.createElement('div');
			this.tempDiv.innerHTML = this.messageHtmlTemplate;
			var aNewHtmlElement = this.tempDiv.childNodes[0];
			this.loadAMessage(aNewHtmlElement, aMessage);
			this.html.insertBefore(aNewHtmlElement, this.html.firstChild);
		}
	};
	
	this.loadAMessage = function(iHtmlElement, iMessage) {
		var aHtmlMessage, m;
		// tags
		if(iMessage.tags) {
			for(var t in iMessage.tags) {
				iHtmlElement.className += " "+iMessage.tags[t];
			}
		}
		// author
		if(iMessage.author) {
			aHtmlMessages = iHtmlElement.getElementsByClassName("author");
			for(m in aHtmlMessages) aHtmlMessages[m].innerHTML = iMessage.author;
		}
		// server date
		if(iMessage.sdate) {
			this.serverDate = new Date(iMessage.sdate);
		}
		// date
		if(iMessage.date) {
			aHtmlMessages = iHtmlElement.getElementsByClassName("date");
			var aDate = new Date(iMessage.date);
			var aDateDiff = this.serverDate - aDate;
			for(m in aHtmlMessages) {
				aHtmlMessages[m].innerHTML = this.formatDateDiff(aDateDiff);
				aHtmlMessages[m].title = this.formatDate(aDate);
			}
		}
		// text
		if(iMessage.txt) {
			var aHtmlMessages = iHtmlElement.getElementsByClassName("txt");
			for(m in aHtmlMessages) aHtmlMessages[m].innerHTML = iMessage.txt;
		}
	};
	
	// convert a date diff (in milliseconds) into a formatted str field
	this.dateDiffUnits = [
			{str:"year", nb:1000*60*60*24*365},
			{str:"month", nb:1000*60*60*24*30},
			{str:"week", nb:1000*60*60*24*7},
			{str:"day", nb:1000*60*60*24},
			{str:"hour", nb:1000*60*60},
			{str:"minute", nb:1000*60},
			{str:"second", nb:1000}];
	this.formatDateDiff = function(iDateDiff) {
		var aDateDiffUnit;
		for(var u in this.dateDiffUnits) {
			aDateDiffUnit = this.dateDiffUnits[u];
			if(iDateDiff >= aDateDiffUnit.nb) break;
		}
		var aNbUnit = Math.floor(iDateDiff/aDateDiffUnit.nb);
		return ""+aNbUnit+" "+aDateDiffUnit.str+((aNbUnit>1)?"s":"")+" ago";
	};
	this.formatDate = function(iGmtDate) {
		return iGmtDate.toLocaleString();
	};
	
	this.init(iId);
};
