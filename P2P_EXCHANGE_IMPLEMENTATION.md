# P2P Currency Exchange - Complete Implementation Guide

## Overview
This document outlines the complete implementation of the P2P Currency Exchange system with Smart Escrow and Atomic Settlement as specified.

## Database Schema (✅ COMPLETED)

### Tables Created:
1. **p2p_offers** - Stores user-created exchange offers
2. **p2p_exchange_requests** - Tracks exchange requests from counterparties
3. **escrow_transactions** - Manages smart escrow logic and confirmations
4. **atomic_instructions** - Stores atomic payment instructions for institutions

## System Workflow

### Phase 1: Counterparty Initiates Exchange Request
**Trigger:** User clicks "Start Exchange" on a P2P offer

**Dialog Fields:**
1. Source Account (select) - Account to send selling currency
2. Destination Account (select) - Account to receive new currency
3. Sell Currency (auto-filled from offer)
4. Buy Currency (auto-filled from offer)
5. Amount (Sell) (input) - Amount counterparty wants to exchange
6. Receive Amount (auto-calculated) - Based on exchange rate

**Actions:**
- Cancel Button - Closes dialog
- Request Button - Creates P2PExchangeRequest record and sends notification to offer creator

### Phase 2: Initiator Receives Notification
**Notification Content:**
- "New Currency Exchange Request from [Counterparty Name]"
- Badge count on notification bell icon

**Dialog Fields (All Auto-Filled, Non-Editable):**
1. Counterparty Name
2. Counterparty ID (platform-generated unique ID)
3. Buy Currency (what counterparty wants to sell)
4. Amount (what counterparty wants to exchange)
5. Sell Currency (what counterparty wants to buy)
6. Amount (Sell) (what counterparty will receive)
7. Source Account (initiator's account to send from)
8. Destination Account (initiator's account to receive to)

**Actions:**
- Decline Button - Updates request status to 'declined'
- Accept Button - Triggers Smart Escrow Preconditions

### Phase 3: Smart Escrow Preconditions
**Automated Checks (milliseconds):**

1. **Balance Sufficiency Check**
   - Initiator has sufficient balance + fees
   - Counterparty has sufficient balance + fees

2. **AML Check**
   - Transaction amount within limits
   - User transaction history review
   - Velocity checks

3. **Sanctions Check**
   - User not on sanctions lists
   - Countries not sanctioned
   - Institutions not blacklisted

4. **Behavioral Rules**
   - User account age
   - Trust score threshold
   - Previous transaction success rate

5. **Jurisdiction FX Rules**
   - Currency pair allowed in jurisdictions
   - Regulatory compliance for both countries
   - Cross-border restrictions

**Outcomes:**
- ✅ All Pass → Proceed to Phase 4
- ❌ Any Fail → Auto-decline + notify both parties with reason

### Phase 4: Trade Summary & PIN Confirmation
**Both Users Receive Trade Summary:**

**Fields Displayed:**
1. Amount | Currency (to be sent)
2. PANÉTA Fee (0.99% of amount)
3. Total Amount (amount + fee to be debited)
4. Enter PIN (input field)

**Actions:**
- Cancel Button - Cancels the exchange
- Confirm & Execute Exchange Button - Records confirmation with PIN

**System Behavior:**
- Waits 0-10 minutes for both parties to confirm
- If one party confirms, holds instruction
- If both confirm within window → Proceed to Phase 5
- If timeout → Auto-cancel and notify both parties

### Phase 5: Atomic Instruction Generation
**System Generates 2 Atomic Instructions:**

**Instruction 1 (Initiator):**
```json
{
  "user_id": "initiator_id",
  "source_account_id": "initiator_source",
  "destination_account_id": "counterparty_destination",
  "currency": "sell_currency",
  "amount": "sell_amount",
  "fee": "paneta_fee",
  "total_amount": "amount + fee",
  "instruction_payload": {
    "transaction_type": "p2p_exchange",
    "exchange_id": "escrow_transaction_id",
    "timestamp": "utc_timestamp",
    "beneficiary": "counterparty_account_details"
  },
  "signed_hash": "cryptographic_signature"
}
```

**Instruction 2 (Counterparty):**
```json
{
  "user_id": "counterparty_id",
  "source_account_id": "counterparty_source",
  "destination_account_id": "initiator_destination",
  "currency": "buy_currency",
  "amount": "buy_amount",
  "fee": "paneta_fee",
  "total_amount": "amount + fee",
  "instruction_payload": {
    "transaction_type": "p2p_exchange",
    "exchange_id": "escrow_transaction_id",
    "timestamp": "utc_timestamp",
    "beneficiary": "initiator_account_details"
  },
  "signed_hash": "cryptographic_signature"
}
```

### Phase 6: Execution & Settlement
**System Actions:**
1. Simultaneously sends both atomic instructions to respective institutions
2. Updates instruction status to 'sent_to_institution'
3. Awaits acknowledgment from institutions
4. Updates escrow_transaction status to 'executing'
5. Monitors settlement status
6. Updates to 'completed' when both settle
7. Notifies both users of successful exchange

## Implementation Checklist

### Backend Components

#### Models (app/Models/)
- [ ] P2POffer.php
- [ ] P2PExchangeRequest.php
- [ ] EscrowTransaction.php
- [ ] AtomicInstruction.php

#### Services (app/Services/)
- [ ] SmartEscrowService.php
  - [ ] checkBalanceSufficiency()
  - [ ] performAMLCheck()
  - [ ] performSanctionsCheck()
  - [ ] checkBehavioralRules()
  - [ ] checkJurisdictionRules()
  - [ ] runAllPreconditions()

- [ ] AtomicInstructionService.php
  - [ ] generateInstruction()
  - [ ] signInstruction()
  - [ ] sendToInstitution()
  - [ ] monitorSettlement()

#### Controllers (app/Http/Controllers/Paneta/)
- [ ] P2PExchangeController.php
  - [ ] startExchange() - Create exchange request
  - [ ] acceptRequest() - Initiator accepts
  - [ ] declineRequest() - Initiator declines
  - [ ] confirmTrade() - User confirms with PIN
  - [ ] getExchangeRequests() - Fetch user's requests

#### Routes (routes/web.php)
```php
Route::middleware(['auth'])->prefix('paneta/p2p-exchange')->group(function () {
    Route::post('/request', [P2PExchangeController::class, 'startExchange']);
    Route::post('/{request}/accept', [P2PExchangeController::class, 'acceptRequest']);
    Route::post('/{request}/decline', [P2PExchangeController::class, 'declineRequest']);
    Route::post('/{escrow}/confirm', [P2PExchangeController::class, 'confirmTrade']);
    Route::get('/requests', [P2PExchangeController::class, 'getExchangeRequests']);
});
```

### Frontend Components

#### Vue Components (resources/js/pages/Paneta/)
- [ ] Update CurrencyExchange.vue
  - [ ] Add Start Exchange Dialog
  - [ ] Add Exchange Request Notification Handler
  - [ ] Add Accept/Decline Dialog for Initiators
  - [ ] Add Trade Summary Dialog
  - [ ] Add PIN Confirmation Input

#### Notification System
- [ ] Add exchange request notifications to DashboardHeader.vue
- [ ] Create notification badge counter
- [ ] Add notification click handler to show request details

## Fee Structure
- PANÉTA Platform Fee: 0.99% of transaction amount
- Applied to both parties
- Deducted from source account along with principal amount

## Security Features
1. End-to-end encrypted chat for rate negotiation
2. Cryptographically signed atomic instructions
3. PIN confirmation for trade execution
4. Smart escrow holds until both parties confirm
5. Automatic timeout and cancellation
6. Comprehensive audit trail

## Status Flow

### P2PExchangeRequest
1. pending → accepted/declined/expired
2. accepted → completed/failed

### EscrowTransaction
1. precondition_check → awaiting_confirmation/failed
2. awaiting_confirmation → confirmed/failed
3. confirmed → executing
4. executing → completed/failed

### AtomicInstruction
1. generated → sent_to_institution
2. sent_to_institution → acknowledged
3. acknowledged → executed
4. executed → settled/failed

## Next Steps

1. Create all model files with relationships
2. Implement SmartEscrowService with all precondition checks
3. Build P2PExchangeController with all endpoints
4. Update CurrencyExchange.vue with all dialogs
5. Integrate notification system
6. Test complete workflow end-to-end
7. Add error handling and edge cases
8. Implement timeout mechanisms
9. Add comprehensive logging

## Notes
- Zero-custody architecture maintained throughout
- PANÉTA never holds funds
- Instructions sent directly to institutions
- Users maintain full control of their accounts
- Atomic settlement ensures both legs execute or neither
