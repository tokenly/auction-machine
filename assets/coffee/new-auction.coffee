do ($=jQuery) ->


    # ####################################################################################################################


    init = ()->
        $('a[data-add-token]').on 'click', (e)->
            e.preventDefault()
            addAnotherWinningTokenSection($(this))

        setupTimezonesAndDates()

    # ####################################################################################################################

    addAnotherWinningTokenSection = (el)->
        firstSection = $('[data-winning-token-section]').first()
        lastSection = $('[data-winning-token-section]').last()

        newSection = firstSection.clone()
        newOffset = parseInt(lastSection.data('offset')) + 1
        newSection.data('offset', newOffset)
        $('input', newSection).val('')
        $('label', newSection).each ()->
            $(this).attr('for', $(this).attr('for').replace('_0', '_'+newOffset))
        $('input', newSection).each ()->
            $(this).attr('name', $(this).attr('name').replace('_0', '_'+newOffset))
            $(this).attr('id', $(this).attr('id').replace('_0', '_'+newOffset))
        newSection.insertAfter(lastSection)

    # ####################################################################################################################

    setupTimezonesAndDates = ()->
        Date.parseDate = (input, format) ->
            moment(input, format).toDate()

        Date::dateFormat = (format) ->
            moment(this).format format

        $("#startDate").datetimepicker {
            format: "MM.DD.YYYY h:mm a"
            formatTime: "h:mm a"
            formatDate: "MM.DD.YYYY"
        }

        $("#endDate").datetimepicker {
            format: "MM.DD.YYYY h:mm a"
            formatTime: "h:mm a"
            formatDate: "MM.DD.YYYY"
        }
    
        tzName = $("#TimezoneInput").val()
        if not tzName
            tzName = moment().format("Z")
        $("#TimezoneInput").val tzName

        tzLongName = $("#LongTimezoneInput").val()
        if not tzLongName
            tzLongName = window.jstz.determine().name()
        $("#LongTimezoneInput").val tzLongName


        $("span[data-timezone-label]").html tzName
        $("span[data-timezone-label]").attr('title', tzLongName)


    # ####################################################################################################################

    init()

