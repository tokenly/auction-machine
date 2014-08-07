do ($=jQuery)->

    ANIM_SPEED = 120

    init = ()->
        bindEvents()


    bindEvents = ()->
        # find all items
        $('*[data-role=toggle-link]').on 'click', (e)->
            e.preventDefault()
            containerEl = $(this).closest('*[data-role=entry-container]')
            detailsEl = $('*[data-role=details]', containerEl)
            toggleDetails(detailsEl)
            return


    toggleDetails = (detailsEl, firstTime=false)->
        if detailsEl.data('show-state') == 'visible'
            hideDetails(detailsEl, firstTime)
            detailsEl.data('show-state', 'hidden')
        else
            showDetails(detailsEl, firstTime)
            detailsEl.data('show-state', 'visible')
        return
    
    showDetails = (detailsEl, firstTime=false)->
        detailsEl.slideDown ANIM_SPEED
        return

    hideDetails = (detailsEl, firstTime=false)->
        detailsEl.slideUp ANIM_SPEED
        return



    # ###########################################################################
    init()

    return