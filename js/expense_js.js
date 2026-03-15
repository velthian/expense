
$( function() {
    $( "#datepicker" ).datepicker({
            changeMonth: true,
            changeYear: true,
            maxDate: null,
            dateFormat: "yy-mm-dd"
    });

    $( ".datepicker" ).datepicker({
            changeMonth: true,
            changeYear: true,
            maxDate: null,
            dateFormat: "yy-mm-dd"
    });
});

$.fn.centerInBody = function () {
    const $window = $(window);
    const $element = this;

    const top = Math.max(0, ($window.height() - $element.outerHeight()) / 2 + $(window).scrollTop());
    const left = Math.max(0, ($window.width() - $element.outerWidth()) / 2 + $(window).scrollLeft());

    $element.css({
        position: 'absolute',
        top: top + 'px',
        left: left + 'px'
    });

    return this;
};


$.fn.center = function () {
this.css("position","fixed");
this.css("top", ( $(window).height() - this.height() ) / 2  + "px");
this.css("left", ( $(window).width() - this.outerWidth() ) / 2 + "px");
return this;
}

/*center horizontally only*/

$.fn.horiz_center = function () {
this.css("position","absolute");
this.css("left", ( $(window).width() - this.outerWidth() ) / 2 + "px");
return this;
}

/*center absolute ie. center wrt parent */
$.fn.center_abs = function () {
this.css("position","absolute");
this.css("top", ( $(window).height() - this.outerHeight() ) / 2  + "px");
this.css("left", ( $(window).width() - this.outerWidth() ) / 2 + "px");
return this;
}

/*format according to indian currency standards */
function formatINR(x) {
    x = Number(x) || 0;
    return x.toLocaleString('en-IN', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    });
}