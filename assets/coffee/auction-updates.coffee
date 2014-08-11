do ($=jQuery) ->
    # FADE_SPEED = 75
    bidEntries = {}

    BID_EL_HEIGHT = 66


    numeral = window.numeral


    # ####################################################################################################################

    AuctionSocket = window.AuctionSocket = {}
    AuctionSocket.connect = (auctionSlug, isAdmin)->

        socket = window.io.connect()

        socket.on 'status', (data)->
            # console.log('status: '+data.state)
            return

        socket.on 'auction-update', (data)->
            # console.log "update",data
            setTimeout ()->
                updateAuction(data, isAdmin)
            , 1
            return

        socket.on 'disconnect', ()->
            # console.log('disconnected from server')
            return

        socket.on 'connect', ()->
            # console.log('state: connected')
            socket.emit 'listen', auctionSlug
            return

        socket.on 'error', (e)->
            console.error "ERROR",e.stack

    init = ()->
        bidEntries = initBidsByAddress()


    # ####################################################################################################################

    updateAuction = (data, isAdmin)->
        # console.log "updateAuction data",data
    
        # console.log "updateBids"
        updateBids(data.state.bids, data.state.accounts)
        # console.log "updatePageVars"
        updatePageVars(data.state, data.auction, data.meta)
        if isAdmin
            # console.log "updateAdminPageVars"
            updateAdminPageVars(data.state, data.auction, data.meta)
        

    updateBids = (bids, accounts)->

        # first update values
        for bid in bids
            bidEntry = bidEntries[bid.address]
            if not bidEntry?
                # new bid - create it first
                bidEntry = generateNewBidEntry(bid)

            updateBidElement(bidEntry, bid)

        reorderBidEntries()

    updateBidElement = (bidEntry, bid)->
        # set rank data
        bidEntry.el.data('rank', bid.rank)

        # update winning class
        if bid.rank == 0
            bidEntry.el.addClass('winning')
        else
            bidEntry.el.removeClass('winning')

        # update the qty
        $('span[data-qty]', bidEntry.el).html(formatCurrency(bid.amount))

        # update the amounts
        # console.log "bid.account=",bid.account
        for amountType in ['prebid','late']
            amount = bid.account[amountType]
            # console.log "amount=",amount
            el = $("span[data-amount-#{amountType}]", bidEntry.el)
            if amount > 0
                el.html(formatCurrency(amount)).parent().show()
            else
                el.html('').parent().hide()
            
        

        # update rank badge
        rankBadgeEl = $('span[data-rank-badge]', bidEntry.el)
        html = if (bid.rank == 0) then rankBadgeEl.data('leader-text') else "##{bid.rank+1}"
        rankBadgeEl.html(html)

        # update rank icon
        rankIconEl = $('*[data-rank-icon]', bidEntry.el)
        if bid.rank == 0
            rankIconEl.removeClass(rankIconEl.data('rank-icon-other')).addClass(rankIconEl.data('rank-icon-first'))
        else
            rankIconEl.removeClass(rankIconEl.data('rank-icon-first')).addClass(rankIconEl.data('rank-icon-other'))

    updatePageVars = (state, auction, meta)->
        # console.log "updatePageVars (1)"
        bounty = state.bounty
        # console.log "updatePageVars (1b)"
        nextMinBid = state.bids[0]?.amount + auction.minBidIncrement
        # console.log "updatePageVars (1c) nextMinBid=",nextMinBid
        nextPayment = nextMinBid + bounty
        # console.log "updatePageVars (2)"

        # vars
        $('*[data-next-min-bid]').html(formatCurrency(nextMinBid))
        $('*[data-bounty]').html(formatCurrency(bounty))
        $('*[data-next-payment]').html(formatCurrency(nextPayment))

        # console.log "updatePageVars (3)"
        for stateField in ['blockId',]
            el = $("""*[data-state-field="#{stateField}"]""")
            continue if not el.length
            value = formatValueByElementSettings(state[stateField], el)
            if state.hasMempoolTransactions
                value = value + ' <span class="pending">pending</span>'
                
            el.html(value)

        for metaField in ['lastBlockSeen',]
            el = $("""*[data-meta-field="#{metaField}"]""")
            continue if not el.length
            value = formatValueByElementSettings(meta[metaField], el)
            el.html(value)

        # build receipts
        buildReceiptsDisplay(auction.payoutReceipts)

        # status
        # console.log "updatePageVars (4)"
        $('*[data-auction-status]').hide()
        phaseToShow = state.timePhase
        # console.log "state.active=",state.active
        if state.timePhase == 'live' and !state.active
            phaseToShow = 'prebid'
        $("""*[data-auction-status="#{phaseToShow}"]""").show()

        # console.log "state.bids?.length",state.bids?.length
        if state.bids?.length
            $('*[data-no-bids]').hide()
        else
            $('*[data-no-bids]').show()


    initBidsByAddress = ()->
        bidEntries = {}
        counter = 0

        offset = 0
        $("li[data-bid-address]").each (index)->
            el = $(this)
            bidEntries[el.data('bid-address')] = {
                el: el
            }

            # yPos = el.position().top
            yPos = index * BID_EL_HEIGHT + offset
            el.data('yPos', yPos)
            el.css({position:'absolute',top:yPos,width:el.outerWidth()})

            ++counter
        
        $('ul.ordered-bids').height(counter * BID_EL_HEIGHT)

        return bidEntries

    reorderBidEntries = ()->
        liEls = $('ul.ordered-bids > li')
        liEls.tsort({data:'rank', order:'asc'}).each (i,el)->
            $El = $(el)
            width = $El.outerWidth()
            fromTop = $.data(el,'h')
            toTop = i * BID_EL_HEIGHT
            $El.css({position:'absolute',top:fromTop,width:width}).animate({top:toTop},500)


    formatCurrency = (amount)->
        # console.log "amount=",amount
        return '' if not amount?
        return '' if isNaN(amount)
        return numeral(amount / 100000000).format('0,0.[00000000]')

    generateNewBidEntry = (bid)->
        html = """
            <li class="bid" data-bid-address="#{bid.address}" data-rank="#{bid.rank}">
                <span class="right badge" data-rank-badge data-leader-text="Leader">##{bid.rank + 1}</span>
                <i data-rank-icon data-rank-icon-first="fa-rocket" data-rank-icon-other="fa-user" class="fa fa-user fa-2x left"></i>
                <div class="amount">
                    <span data-qty>#{formatCurrency(bid.amount)}</span> #{bid.token}
                    <span class="prebid" style="display: none;">(<span data-amount-prebid>#{formatCurrency(bid.account.prebid)}</span> pre-bid)</span>
                    <span class="late" style="display: none;">(<span data-amount-late>#{formatCurrency(bid.account.late)}</span> late)</span>
                </div>
                <div class="address">#{bid.address}</div>
            </li>
        """

        ul = $('ul.ordered-bids')
        el = $(html).appendTo(ul)
        itemCount = $('li', ul).length
        yPos = itemCount * BID_EL_HEIGHT
        el.data('yPos', yPos)
        newBidEntry = {
            el: el
        }
        bidEntries[bid.address] = newBidEntry

        ul.height(itemCount * BID_EL_HEIGHT)

        return newBidEntry


    # ###################################################
    # Receipts
    
    buildReceiptsDisplay = (receipts)->
        list = $('.payout-transactions-list').empty()

        for receipt in receipts
            src = """
            <div class="receipt" data-receipt>
                Sent
                <span data-receipt-field="amountSent" data-currency>#{ if receipt.amountSent? then formatCurrency(receipt.amountSent) else formatCurrency(receipt.payout.amount) }</span>
                <span <span data-payout-field="token">#{ receipt.payout.token }</span>
                to
                <span class="address addressSmall" data-payout-field="address">#{ receipt.payout.address }</span>.
                <span class="transaction-link right"><a href="https://blockchain.info/tx/#{ receipt.transactionId }" target="_blank" data-receipt-field="transactionLink">View Transaction <i class="fa fa-external-link"></i></a></span>
            </div>
            """

            list.append($(src))

        if receipts.length
            $('#PayoutTransactions').show()
        else
            $('#PayoutTransactions').hide()

        return


            
                    

    # ###################################################
    # formatter
    
    formatValueByElementSettings = (value, el)->
        return value if not el.length
        # console.log "formatValueByElementSettings"
        if el.is("[data-bool]")
            if value
                value = "Yes"
                el.addClass('yes').removeClass('no')
            else
                value = "No"
                el.addClass('no').removeClass('yes')
        if el.is("[data-currency]")
            value = formatCurrency(value)
        if el.is("[data-prize-token-list]")
            value = buildPrizeTokensAppliedList(value)

        return value

    # ###################################################
    # Admin

    updateAdminPageVars = (state, auction, meta)->
        # console.log "update admin"
        for stateField in ['btcFeeSatisfied','bidTokenFeeSatisfied','prizeTokensSatisfied','btcFeeApplied','bidTokenFeeApplied','active','prizeTokensApplied',]
            el = $("""*[data-state-field="#{stateField}"]""")
            continue if not el.length
            value = formatValueByElementSettings(state[stateField], el)
            el.html(value)

    buildPrizeTokensAppliedList = (prizeTokensApplied)->
        return '' if not prizeTokensApplied

        # build prize tokens applied list
        # console.log "begin list"
        list = for token, amount of prizeTokensApplied
            "#{formatCurrency(amount)} #{token} received"
        return '' if list.length == 0
        return "(#{ list.join(', ') })"
        


    # ####################################################################################################################

    init()

