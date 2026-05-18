LibertyComment = {
	FORM_DIV_ID: 'edit_comments',
	FORM_ID: 'editcomment-form',
	REPLY_ID: null,

	attachForm: function(elm, reply_id, root_id) {
		LibertyComment.REPLY_ID = reply_id;
		LibertyComment.cancelComment();
		var $formDiv = $('#' + LibertyComment.FORM_DIV_ID).detach();
		$(elm).after($formDiv);

		var form = document.getElementById(LibertyComment.FORM_ID);
		if (!form.parent_id) {
			$('<input>').attr({ type: 'hidden', name: 'parent_id', value: root_id }).appendTo(form);
		}
		form.parent_id.value = LibertyComment.ROOT_ID = root_id;
		form.post_comment_reply_id.value = reply_id;
		form.post_comment_id.value = '';

		$formDiv.show();
		$('html,body').animate({ scrollTop: $formDiv.offset().top });
	},

	resetForm: function() {
		document.getElementById(LibertyComment.FORM_ID).reset();
	},

	detachForm: function() {
		$('#' + LibertyComment.FORM_DIV_ID).hide().appendTo(document.body);
	},

	sendRequest: function(formdata, callback) {
		$.ajax({
			url: bitRootUrl + 'liberty/ajax_comments.php',
			type: 'POST',
			data: formdata,
			dataType: 'xml',
			success: callback
		});
	},

	previewComment: function() {
		var LC = LibertyComment;
		$.each(LC.prepRequestSrvc, function(i, fn) { fn(LC.FORM_ID); });
		LC.sendRequest($('#' + LC.FORM_ID).serialize(), LC.displayPreview);
	},

	postComment: function() {
		var LC = LibertyComment;
		$.each(LC.prepRequestSrvc, function(i, fn) { fn(LC.FORM_ID); });
		LC.sendRequest($('#' + LC.FORM_ID).serialize() + '&post_comment_submit=1', LC.checkRslt);
	},

	cancelComment: function(ani) {
		LibertyComment.cancelPreview(true);
		if (ani === true) {
			$('#' + LibertyComment.FORM_DIV_ID).slideUp(function() {
				LibertyComment.detachForm();
				LibertyComment.resetForm();
				var $replyDiv = $('#comment_' + LibertyComment.REPLY_ID);
				if ($replyDiv.length) {
					$('html,body').animate({ scrollTop: $replyDiv.offset().top });
				}
			});
		} else {
			LibertyComment.detachForm();
			LibertyComment.resetForm();
		}
	},

	cancelPreview: function(ani) {
		var $preview = $('#comment_preview');
		if ($preview.length) {
			if (ani === true) {
				$preview.slideUp(function() { $preview.remove(); });
			} else {
				$preview.remove();
			}
		}
	},

	displayPreview: function(xml) {
		LibertyComment.cancelPreview();
		var $preview = $('<div>').attr('id', 'comment_preview')
			.css('marginLeft', (LibertyComment.REPLY_ID != LibertyComment.ROOT_ID) ? '20px' : '0')
			.html($(xml).find('content').text())
			.hide();
		$('#' + LibertyComment.FORM_DIV_ID).before($preview);
		$preview.slideDown(function() {
			$('html,body').animate({ scrollTop: $preview.offset().top });
		});
	},

	checkRslt: function(xml) {
		if ($(xml).find('code').text() === '200') {
			LibertyComment.displayComment(xml);
		} else {
			LibertyComment.displayPreview(xml);
		}
	},

	displayComment: function(xml) {
		var $comment = $('<div>')
			.css('marginLeft', (LibertyComment.REPLY_ID != LibertyComment.ROOT_ID) ? '20px' : '0')
			.html($(xml).find('content').text())
			.hide();

		if (LibertyComment.SORT_MODE === 'commentDate_asc') {
			$('#comment_' + LibertyComment.REPLY_ID + '_footer').before($comment);
		} else {
			$('#comment_' + LibertyComment.REPLY_ID).after($comment);
		}

		LibertyComment.cancelPreview(true);
		$('#' + LibertyComment.FORM_DIV_ID).slideUp(function() {
			LibertyComment.detachForm();
			LibertyComment.resetForm();
			$comment.slideDown(function() {
				$('html,body').animate({ scrollTop: $comment.offset().top });
			});
		});
	},

	prepRequestSrvc: []
};
