/**
 * jquery.amend.js is a plugin to manage amendments for any text divided 
 * into clearly identified parts (using custom attribute). It requires a 
 * persistance system to store data and google-diff-match-patch library 
 * (http://code.google.com/p/google-diff-match-patch/) to visualize amendments.
 * 
 * Plugin configuration requires the following properties to work properly:
 * 
 * - attrname: html attribute containing text id ("data-reference" by default)
 * - auto: show amend form when user clicks on text (false by default)
 * - container: custom amendments container (optional)
 * - fields: custom amendment fields map ~ data structure (see default opts)
 * - listeners: collection of listeners
 * - statuses: custom amendment status map (see default opts)
 * - style: custom class names for form elements (see default opts)
 * - t: translate function with text as 1st argument and tag as 2nd (optional)
 *
 * HTML should contain only headers and paragraphs identified by custom attr
 * and CSS could be customized using config and library specific selectors.
 */

(function(window,document,$,undefined) {
  'use strict';

  var error, defaultOpts, validateOpts;

  error = function(msg) {
    throw new Error('ERROR: jquery.amend: ' + msg);
  };

  defaultOpts = {
    'attrname': 'data-reference',
    'auto': false,
    'container': null,
    'fields': {
      'id': 'id',               // amendment unique id
      'reference': 'reference', // original text id
      'amendment': 'amendment', // modified or new text
      'extra': 'extra',         // new text flag
      'reason': 'reason',       // justification
      'author': 'author',       // username
      'status': 'status',       // approval status
      'date': 'date'            // creation date
    },
    'listeners': [],
    'style': {
      'form':     'amend-form',
      'label':    'amend-label',
      'input':    'amend-input',
      'textarea': 'amend-textarea',
      'preview':  'amend-textarea',
      'button':   {
        'standard':  'amend-button',
        'submit':    'amend-submit',
        'cancel':    'amend-cancel',
        'remove':    'amend-delete'
      }
    },
    statuses: {
      'pending':  'Awaiting review',
      'approved': 'Amendment approved',
      'rejected': 'Amendment rejected'
    },
    't': function(text) { return text; }
  };
  
  validateOpts = function(options) {
    if (!(options && $.isPlainObject(options))) {
      return false;
    }
    $.each(options, function(name) {
      if (defaultOpts[name] === undefined) {
        return error('Unknown option: "' + name + '"');
      } else if (name === 't' && !$.isFunction(options[name])) {
        return error('Option "' + name + '" is not a function.');
      }
    });
    return true;
  };

  var AmendManager = (function() {

    function AmendManager(elem, options, json) {
      // Initialize configuration    
      validateOpts(options);
      $.extend(defaultOpts, options);
      for (var i in defaultOpts) {
        this[i] = defaultOpts[i];
      }
      
      // System
      this.dmp = new diff_match_patch();
      
      // Preprocess data
      var data = {};
      for (i in json) {
        // Initialize list
        if (!data[json[i][this.fields['reference']]]) {
          data[json[i][this.fields['reference']]] = [];
        }
        // Add element
        data[json[i][this.fields['reference']]].push(json[i]);
      }
      
      // Start amendments system
      this.initHtml(elem, data);
      this.initEvents(elem);
    }

    /**
     * Initialize HTML
     */
    AmendManager.prototype.initHtml = function(elem, data) {
      var self = this,
          $container;
      
      if (this.container) {
        // Use custom container
        $container = $(this.container);
      } else {
        // Build container
        $container = $('<div>', {
          'class': 'jqa-container'
        }).hide();
        // Add to DOM
        $(elem).after($container);
      }
      
      $(elem).on('jqa-toggle', function() {
        $container.slideToggle();
      }).on('jqa-render', function(event, dom) {
        $container.append(dom);
        // Alert listeners
        self.notify('jqa-rendered', [dom]);
        self.notify('jqa-counter', [$container.children().length]);
      });
        
      // Add amendments to original text
      var ref = $(elem).attr(this.attrname);
      if (data[ref] !== undefined) {
        this.renderAmendments(elem, data[ref]);
      }
    };
    
    /**
     * Render amendment
     */
    AmendManager.prototype.renderAmendments = function(node, list) {
      // Basic checks
      if (!list instanceof Array || !list.length) {
        return;
      }
      
      var $node = $(node),
          original = $node.text(),
          dom = [];
      
      // Render amendments

      for (var i in list) {
        
        var $ul = $('<ul>', {
          'class': 'amendment'
        }).append($('<li>', {
          'class': 'amendment-text',
          'html': list[i][this.fields['extra']] 
            ? '<span class="plus">[+]</span> ' + list[i][this.fields['amendment']] 
            : this.renderTextDiff(original, list[i][this.fields['amendment']] ? list[i][this.fields['amendment']] : '')
        })).data('json', list[i]);
        
        if (!this.isEmpty(list[i][this.fields['reason']])) {
          $ul.append($('<li>', {
            'class': 'amendment-reason',
            'html': '<span>' + this.t('Reason') + ':</span> ' + 
                      list[i][this.fields['reason']]
          }));
        }
        
        $ul.append($('<li>', {
          'class': 'amendment-creation',
          'html':   '<span>' + this.t('sent by') + '</span> ' + 
                    '<span class="amendment-author">' + 
                        (this.isEmpty(list[i][this.fields['author']]) 
                            ? this.t('anonymous') 
                            : list[i][this.fields['author']]) + 
                    ' </span>' +
                    (this.isEmpty(list[i][this.fields['date']]) 
                        ? ''
                        :   '<span>' + this.t('on') + '</span> ' + 
                            '<span class="amendment-date">' + 
                                list[i][this.fields['date']] + 
                            '</span>')
        })).append($('<li>', {
          'class': 'amendment-status amendment-status-' + list[i][this.fields['status']],
          'html': this.statuses[list[i][this.fields['status']]]
        }));
        
        dom.push($ul);
      }
      
      // Alert listeners
      $node.trigger('jqa-render', [dom]);
    };
    
    /**
     * Initialize events
     */
    AmendManager.prototype.initEvents = function(elem) {
      var self = this,
          isH = $(elem).is(':header'),
          events = 'jqa-new' + (!isH && this.auto ? ' click' : '');
          
      $(elem).on(events, function(event) {
        self.renderForm(elem, isH);
        // Alert listeners
        self.notify('jqa-ready', [isH]);
        // Avoid follow
        event.preventDefault();
        return false;
      });
    };
    
    /**
     * Modify an existing text or create a new one
     */
    AmendManager.prototype.renderForm = function(node, extra) {
      // Get previous data
      var $node = $(node),
          original = this.getOriginalText(node),
          $submitBtn, $cancelBtn, $amendForm;
      
      // Build new form
      $submitBtn = $('<button>', {
        'name': 'submit',
        'html': this.t('Send'),
        'type': 'submit',
        'class': this.style.button.standard + ' ' + this.style.button.submit
      });
      $cancelBtn = $('<button>', {
        'name': 'cancel',
        'html': this.t('Cancel'),
        'type': 'button',
        'class': this.style.button.standard + ' ' + this.style.button.cancel
      });
      $amendForm = $('<form>', {
        'action': '#',
        'class': this.style.form
      }).append($('<textarea>', {
        'name': this.fields['amendment'],
        'class': this.style.textarea
      })).append($submitBtn).append($cancelBtn);
      
      // Form submit
      var self = this;
      
      var submit = function(event, deleteButton) {
        var value = $('textarea', $amendForm).val();
        if ((!self.isEmpty(value) && value !== original) || deleteButton) {
          // Build confirmation form
          self.renderConfirmationForm(node, extra, $amendForm);
        }
        // Alert listeners
        self.notify('jqa-submit');
        // Avoid submit
        event.preventDefault();
        return false;
      };
 
      // Render new form
      if (extra) {
        $amendForm.prepend($('<label>', {
          'for': this.fields['amendment'],
          'html': '<span>' + this.t('Add new text inside') + '</span> ' 
                  + original,
          'class': this.style.label
        }));
      } else {
        $node.hide();
        $('textarea', $amendForm)
          .html(original)
          .css('height', ($node.height()+20) + 'px')
          .focus();
        $amendForm.append($('<button>', {
          'name': 'delete',
          'html': this.t('Delete text'),
          'type': 'button',
          'class': this.style.button.standard + ' ' + this.style.button.remove
        }).click(function(event) {
          // Delete event
          $('textarea', $amendForm).val("");
          submit(event, true);
        }));
      }
      
      $node.after($amendForm);
      
      // Submit event
      $amendForm.submit(submit);
      $submitBtn.click(submit);
      
      // Cancel event
      $cancelBtn.click(function(event) {
        // Reset
        $amendForm.remove();
        $node.show();
        // Alert listeners
        self.notify('jqa-cancel');
        // Avoid submit
        event.preventDefault();
        return false;
      });
    };
    
    /**
     * Confirm amendment
     */
    AmendManager.prototype.renderConfirmationForm = function(node, extra, $oldForm) {
      // Get previous data
      var $node = $(node),
          original = this.getOriginalText(node),
          data = this.getFormData($oldForm),
          $submitBtn, $cancelBtn, $confirmForm;
      
      // Build new form
      $submitBtn = $('<button>', {
        'name': 'submit',
        'html': this.t('Confirm'),
        'type': 'submit',
        'class': this.style.button.standard + ' ' + this.style.button.submit
      });
      $cancelBtn = $('<button>', {
        'name': 'cancel',
        'html': this.t('Cancel'),
        'type': 'button',
        'class': this.style.button.standard + ' ' + this.style.button.cancel
      });
      $confirmForm = $('<form>', {
        'action': '#',
        'class': this.style.form
      }).append(
        $('<div>').append($('<label>', {
          'for': this.fields['amendment'],
          'html': this.t('Amendment'),
          'class': this.style.label
        })).append($('<div>', {
          'html': extra ? data[this.fields['amendment']] 
                  : this.renderTextDiff(original, data[this.fields['amendment']]),
          'class': this.style.preview
        }))
      ).append(
        $('<div>').append($('<label>', {
          'for': this.fields['reason'],
          'html': this.t('Reason'),
          'class': this.style.label
        })).append($('<textarea>', {
          'name': this.fields['reason'],
          'class': this.style.textarea
        }))
      ).append(
        $('<div>').append($('<label>', {
          'for': this.fields['author'],
          'html': this.t('Name'),
          'class': this.style.label
        })).append($('<input>', {
          'name': this.fields['author'],
          'type': 'text',
          'class': this.style.input
        }))
      )
      .append($submitBtn)
      .append($cancelBtn);
      
      // Render new form
      $oldForm.before($confirmForm).remove();
      $('textarea', $confirmForm).focus();
      
      // Form events
      var self = this;

      var submit = function(event) {
        // Create amendment
        $.extend(data, self.getFormData($confirmForm));
        data[self.fields['reference']] = $node.attr(self.attrname);
        data[self.fields['extra']] = extra;
        // Alert listeners (form + data + successCallback)
        self.notify('jqa-confirm', [$confirmForm, data, function(extra) {
          // Reset
          close();
          // Add amendment
          $.extend(data, extra);
          self.renderAmendments(node, [data]);
          // Alert listener
          self.notify('jqa-success');
        }]);
        // Avoid submit
        event.preventDefault();
        return false;
      };

      var close = function() {
        $confirmForm.remove();
        $node.show();        
      };
      
      // Submit event
      $confirmForm.submit(submit);
      $submitBtn.click(submit);
      
      // Cancel event
      $cancelBtn.click(function(event) {
        // Reset
        close();
        // Alert listeners
        self.notify('jqa-cancel');
        // Avoid submit
        event.preventDefault();
        return false;
      });
    };

    /**
     * Get form data
     */
    AmendManager.prototype.getFormData = function($form) {
      var data = {}, params = $form.serializeArray();
      for (var i in params) {
        data[params[i]['name']] = params[i]['value'];
      }
      return data;
    };
    
    /**
     * Render text diff
     */
    AmendManager.prototype.renderTextDiff = function(original, amendment) {
      var diff = this.dmp.diff_main(original, amendment);
      this.dmp.diff_cleanupSemantic(diff);
      return this.dmp.diff_prettyHtml(diff);
    };
    
    /**
     * Var empty?
     */
    AmendManager.prototype.isEmpty = function(variable) {    
      return variable === undefined || variable === ""; 
    };

    /**
     * Get original text
     */
    AmendManager.prototype.getOriginalText = function(node) {
      // Default behaviour
      var text = $(node).text();
      // Custom structure case
      $('.jqa-text', node).each(function(){
        text = $(this).text();
      });
      return text;
    };
    
    /**
     * Add listeners
     */
    AmendManager.prototype.addListener = function(listener) {
      this.listeners.push(listener);
    };

    /**
     * Notify listeners
     */
    AmendManager.prototype.notify = function(type, data) {
      for (var i in this.listeners) {
        $(this.listeners[i]).trigger(type, data);
      }
    };
    
    return AmendManager;

  })();


  $.fn["amend"] = function (options, data) {
    if (!$(this).data("amend")) {
      $(this).data("amend", new AmendManager(this, options, data));
    } else if (console) {
      console.log('Amendments system already initialized on this node!', this);
    }
    return $(this).data("amend");
  };

}(window,document,jQuery));
