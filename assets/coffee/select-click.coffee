# <span data-select-click>Click to select me</span>

do ($=jQuery)->

    selectDomElement = (domEl, win) ->
        doc = win.document
        if win.getSelection and doc.createRange
            sel = win.getSelection()
            range = doc.createRange()
            range.selectNodeContents domEl
            sel.removeAllRanges()
            sel.addRange range
        else if doc.body.createTextRange
            range = doc.body.createTextRange()
            range.moveToElementText domEl
            range.select()
        return


    $("*[data-select-click]").on "click", (e) ->
        selectDomElement this, window
        return
