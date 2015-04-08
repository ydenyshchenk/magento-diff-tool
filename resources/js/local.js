function select(id, el)
{
    name = $(el).html();
    val = $(el).attr('data-value');
    //console.log(val);
    $(id).html(name);
    $(id + '_input').val(val)
}

function showForm(id, el)
{
    $('.diff-form').removeClass('active');
    $('#diff-form-' + id).addClass('active');
    $('.btn-warning').removeClass('btn-warning').addClass('btn-default');
    $(el).removeClass('btn-default').addClass('btn-warning');
}

$(document).ready(function(){
    $('.diff-item').click(function(){
        $(this).toggleClass('active');
    });
});