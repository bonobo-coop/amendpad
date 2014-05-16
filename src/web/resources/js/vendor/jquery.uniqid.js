/**
 * jQuery plugin to generate a random unique id
 */
(function(window,document,$,undefined) {
  'use strict';
  
  var uniqid = {
    /**
     * Returns a random alphanumeric id
     * 
     * @see http://stackoverflow.com/a/6860962
     * 
     * @returns String
     */
    alphanumeric: function() {
      return String.fromCharCode(65 + Math.floor(Math.random() * 26)) + Date.now();
    },
    /**
     * Returns a random v4 UUID of the form xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx, 
     * where each x is replaced with a random hexadecimal digit from 0 to f, and y 
     * is replaced with a random hexadecimal digit from 8 to b.
     * 
     * @see https://gist.github.com/LeverOne/1308368
     * 
     * @returns String
     */  
    uuid: function(
      a,b                // placeholders
    ){
      for(               // loop :)
          b=a='';        // b - result , a - numeric variable
          a++<36;        // 
          b+=a*51&52  // if "a" is not 9 or 14 or 19 or 24
                      ?  //  return a random number or 4
             (
               a^15      // if "a" is not 15
                  ?      // genetate a random number from 0 to 15
               8^Math.random()*
               (a^20?16:4)  // unless "a" is 20, in which case a random number from 8 to 11
                  :
               4            //  otherwise 4
               ).toString(16)
                      :
             '-'            //  in other cases (if "a" is 9,14,19,24) insert "-"
          );
      return b;
    }
  };
  
  $.fn["uniqid"] = function (useUUID, replace) {
    return this.each(function() {
      if (!this.id || replace) {
        this.id = useUUID ? uniqid.uuid() : uniqid.alphanumeric();
      }
    });
  };
  
}(window,document,jQuery));