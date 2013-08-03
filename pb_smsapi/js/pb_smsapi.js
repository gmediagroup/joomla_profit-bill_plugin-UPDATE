$(function()
{

    // сразу получаем тарифы
    var PB_TARIFFS = [];
    var response = $.parseJSON(JSON_TARIFS);
	
	if (response.success) {
		PB_TARIFFS = response.data;
	}


    function pg_get_countries()
    {
        var tariffs = PB_TARIFFS;
        var countries = [];

        for (i = 0; i < tariffs.length; i ++) {
            countries.push(tariffs[i].country);
        }

        return $.unique(countries);
    }

    function pb_get_tariff(country)
    {
        var tariffs = PB_TARIFFS;
        var tariff = {};

        if ('undefined' == typeof(country)) {
            tariff = tariffs.slice(0, 1);
        } else {
            for (i = 0; i < tariffs.length; i ++) {
                if (tariffs[i].country == country) {
                    tariff = tariffs[i];
                    break;
                }
            }
        }

        return tariff;
    }

    function pb_show_error_message()
    {
        $('#error_message', '#pb_smsapi_dialog').slideDown('slow', function()
        {
            $(this).removeClass('hidden');
        });
    }

    function pb_hide_error_message(slide)
    {
        if ('undefined' == typeof(slide)) {
            slide = true;
        }

        if (slide) {
            $('#error_message', '#pb_smsapi_dialog').slideUp('slow', function()
            {
                $(this).addClass('hidden');
            });
        } else {
            $('#error_message', '#pb_smsapi_dialog').addClass('hidden').hide();
        }
    }

    // показываем попап
    $('.pb_smsapi_hidden_content_trigger').bind('click', function(event)
    {
        var $link = $(this);

        $('#pb_smsapi_dialog').dialog({
            width: 800,
            modal: true,
            resizable: false,
            open: function(event, ui)
            {
                // инициализируем селект странами
                var $countries_select = $('#country', '#pb_smsapi_dialog');
                $('option', $countries_select).remove();
                $.each(pg_get_countries(), function(index, value)
                {
                    var option = '<option value="' + value + '">' + value + '</option>';
                    $countries_select.append(option);
                });

                // подставляем значения тарифа
                $('#country', '#pb_smsapi_dialog').trigger('change');

                // подставляем id сущности, к кот нужен доступ
                $('input[name=article_id]', 'form[name=access]').val($link.data('id'));
            },
            close: function(event, ui)
            {
                pb_hide_error_message(false);
            }
        });


        event.preventDefault();
        return false;
    });

    // подставляем значения тарифа в соответствии с выбранной страной
    $('#country', '#pb_smsapi_dialog').bind('change', function(event)
    {
        var $countries_select = $('#country', '#pb_smsapi_dialog');
        var tariff = pb_get_tariff($countries_select.val());
        
        $('#message', '#pb_smsapi_dialog').text(tariff.message);
        $('#short_number', '#pb_smsapi_dialog').text(tariff.short_number);
        $('#price', '#pb_smsapi_dialog').text(tariff.price);
    });

    // проверяем код
    $('form[name=access]', '#pb_smsapi_dialog').bind('submit', function(event)
    {
        pb_hide_error_message();

        $.ajax({
            type: 'POST',
            global: false,
            dataType: 'json',
            url: $(this).attr('action'),
            data: $(this).serialize(), 
            success: function(response, textStatus, XMLHttpRequest)
            {
                if (response.access) {
                    document.location.reload();
                } else {
                    pb_show_error_message();
                }
            }
        });

        event.preventDefault();
        return false;
    });

});