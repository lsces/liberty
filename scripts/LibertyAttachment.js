LibertyAttachment = {
	fileInputClones: {},
	uploader_under_way: 0,

	uploaderSetup: function(fileid) {
		LibertyAttachment.fileInputClones[fileid] = document.getElementById(fileid).cloneNode(true);
	},

	uploader: function(file, action, waitmsg, frmid, cformid) {
		if (LibertyAttachment.uploader_under_way) {
			alert(waitmsg);
		} else {
			if (LibertyAttachment.preflightCheck(cformid)) {
				LibertyAttachment.uploader_under_way = 1;
				BitBase.showSpinner();
				var old_target = file.form.target;
				var old_action = file.form.action;
				file.form.target = frmid;
				file.form.action = action;
				file.form.submit();
				file.form.target = old_target;
				file.form.action = old_action;
			} else {
				var fileid = file.id;
				LibertyAttachment.fileInputClones[fileid].id = fileid;
				$(file).replaceWith(LibertyAttachment.fileInputClones[fileid]);
				LibertyAttachment.uploaderSetup(fileid);
			}
		}
	},

	preflightCheck: function(cformid) {
		var form = document.getElementById(cformid);
		var t = form.title.value;
		if (!t) {
			alert('Please enter a title for your new content before attempting to upload a file.');
			return false;
		}
		form['liberty_attachments[title]'].value = t;
		return true;
	},

	uploaderComplete: function(frmid, divid, fileid, cformid) {
		if (LibertyAttachment.uploader_under_way) {
			BitBase.hideSpinner();
			var ifrm = document.getElementById(frmid);
			var d = ifrm.contentDocument || (ifrm.contentWindow && ifrm.contentWindow.document) || window.frames[frmid].document;
			if (d.location.href === 'about:blank') {
				return;
			}

			LibertyAttachment.postflightCheck(cformid, d);

			var errMsg = '<div>Sorry, there was a problem retrieving results.</div>';
			var divO = document.getElementById(divid);
			var divR = d.getElementById('result_tab');
			if (divO) {
				divO.innerHTML = divR ? divR.innerHTML : errMsg + 'a';
			}
			divO = document.getElementById(divid + '_tab');
			divR = d.getElementById('result_list');
			if (divO) {
				divO.innerHTML = divR ? divR.innerHTML : errMsg + 'b';
			}

			LibertyAttachment.uploader_under_way = 0;
			var file = document.getElementById(fileid);
			LibertyAttachment.fileInputClones[fileid].id = fileid;
			$(file).replaceWith(LibertyAttachment.fileInputClones[fileid]);
			LibertyAttachment.uploaderSetup(fileid);
		}
	},

	postflightCheck: function(cformid, d) {
		var form = document.getElementById(cformid);
		var cid = d.getElementById('upload_content_id').value;
		if (typeof form.content_id === 'undefined') {
			var i = document.createElement('input');
			i.name = 'content_id';
			i.type = 'hidden';
			i.value = cid;
			form.insertBefore(i, form.firstChild);
		} else {
			form.content_id.value = cid;
		}
		form['liberty_attachments[content_id]'].value = cid;
	}
};
