do ($=jQuery)->
    FADE_SPEED = 75

    init = ()->
        fillDateTimestamps()


    # ####################################################################################################################
    fillDateTimestamps = ()->
        $('*[data-with-timezone]').each ()->
            el = $(this)
            ms = parseInt(el.data('date-timestamp'), 10) * 1000
            m = window.moment(ms)
            el.html(
                '<span class="time">'+
                m.format("M.DD.YYYY h:mm A")+' <span class="tz">'+m.format('Z')+'</span>'+'</span>'
                # m.d.Y g:i a T
            )
            return

    # ####################################################################################################################

    # init
    init()
