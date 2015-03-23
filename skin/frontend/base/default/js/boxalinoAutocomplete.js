Autocompleter.Base.prototype.onKeyPress = function (event) {
    if (this.active)
        switch (event.keyCode) {
            case Event.KEY_TAB:
            case Event.KEY_RETURN:
                $a = jQuery('a.selected');
                if (typeof $a[0] != 'undefined') {
                    window.location = $a.attr('href');
                } else {
                    this.selectEntry();
                }
                Event.stop(event);
            case Event.KEY_ESC:
                this.hide();
                this.active = false;
                Event.stop(event);
                return;
            case Event.KEY_LEFT:
            case Event.KEY_RIGHT:
                return;
            case Event.KEY_UP:
                this.markPrevious();
                this.render(true);
                Event.stop(event);
                return;
            case Event.KEY_DOWN:
                this.markNext();
                this.render(true);
                Event.stop(event);
                return;
        }
    else if (event.keyCode == Event.KEY_TAB || event.keyCode == Event.KEY_RETURN ||
        (Prototype.Browser.WebKit > 0 && event.keyCode == 0)) return;

    this.changed = true;
    this.hasFocus = true;

    if (this.observer) clearTimeout(this.observer);
    this.observer =
        setTimeout(this.onObserverEvent.bind(this), this.options.frequency * 1000);
};

var originalAddClassMethod = Autocompleter.Base.prototype.render;
Autocompleter.Base.prototype.render = function (key) {
    var result = originalAddClassMethod.apply(this, arguments);
    if (typeof key != 'undefined' && key == true) {
        element = jQuery('#search_autocomplete .selected');
        word = element.data('word');

        jQuery('#search_autocomplete .product-autocomplete').each(function () {
            if (jQuery(this).data('word') == word) {
                jQuery(this).removeClass('hide');
                jQuery(this).show();
            } else {
                jQuery(this).addClass('hide');
                jQuery(this).hide();
            }
        })

    }

    return result;
};

var element = null;
var x,y;
Autocompleter.Base.prototype.onHover = function(event){

    element = event;
    jQuery('*[data-word]').removeClass('selected');
    jQuery(event.srcElement).closest('*[data-word]').addClass('selected');

    setTimeout(function(){
        if(event == element){
            el = jQuery(event.srcElement).closest('*[data-word]');
            $el = jQuery(el);

            console.log(event.pageX, x, event.pageY, y);
            if(Math.abs(event.pageX - x) > 50 || Math.abs(event.pageY - y) > 25){
                return;
            }

            word = el.data('word');

            jQuery('#search_autocomplete .product-autocomplete').each(function () {
                if (jQuery(this).data('word') == word) {
                    jQuery(this).removeClass('hide');
                    jQuery(this).show();
                } else {
                    jQuery(this).addClass('hide');
                    jQuery(this).hide();
                }
            });

            jQuery('*[data-word]').removeClass('selected');

        }
    }, 1000);
};




//
jQuery(document).ready(function() {

    jQuery('body').on('mousemove', function (e) {
        x = e.pageX;
        y = e.pageY;
        //console.log(x,y);
    });

});