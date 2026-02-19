# PANÉTA System Flow Documentation

This document details how each major financial operation is handled within the Panéta platform, emphasizing zero-custody compliance, atomic execution, and FX neutrality.

---

## Table of Contents

1. [Account Aggregation (Zero-Custody, Read-Only)](#1-panéta--account-aggregation-zero-custody-read-only)
2. [Send Money (Local/Same Currency)](#2-panéta--send-money-localsame-currency)
3. [Send Money (Cross-Border with FX Auto-Conversion)](#3-panéta--send-money-cross-border-with-fx-auto-conversion)
4. [Payment Request / Receive Money](#4-panéta--payment-request--receive-money)
5. [Merchant Payments (SoftPOS)](#5-panéta--merchant-payments-softpos)
6. [Merchant Payments Cross-Border / FX Auto-Conversion](#6-merchant-payments--cross-border--fx-auto-conversion)
7. [Peer-to-Peer FX Exchange (Smart Escrow)](#7-panéta--peer-to-peer-fx-exchange-smart-escrow)
8. [Peer-to-Global FX Marketplace (Neutral Liquidity)](#8-panéta--peer-to-global-fx-marketplace-neutral-liquidity)
9. [Cross-Cutting Compliance Services](#cross-cutting-compliance-services)

---

## 1. PANÉTA — ACCOUNT AGGREGATION (Zero-Custody, Read-Only)

### Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                    ZERO-CUSTODY AGGREGATION FLOW                     │
└─────────────────────────────────────────────────────────────────────┘
     User                    PANÉTA                    Institution
       │                       │                            │
       │──Link Account────────▶│                            │
       │                       │──OAuth Redirect───────────▶│
       │                       │◀──Auth Code + Consent──────│
       │                       │                            │
       │                       │  ConsentScopeGuard         │
       │                       │  ├─validateReadOnlyScope() │
       │                       │  └─blockPrivilegeEscalation│
       │                       │                            │
       │                       │  TokenVaultService         │
       │                       │  └─storeToken(encrypted)   │
       │                       │                            │
       │◀──Account Linked──────│                            │
       │                       │                            │
       │──View Balances───────▶│                            │
       │                       │  AggregationEngine         │
       │                       │  └─refreshUserAccounts()   │
       │                       │     └─READ-ONLY fetch─────▶│
       │                       │◀────Account Data───────────│
       │◀──Aggregated View─────│                            │
```

### Key Services

| Service | Responsibility |
|---------|----------------|
| `ConsentService` | Initiates OAuth consent flow, generates tokens |
| `ConsentScopeGuard` | **Enforces read-only scopes**, blocks write escalation |
| `TokenVaultService` | Encrypts and stores institution tokens |
| `AggregationEngine` | Fetches and normalizes data from institutions |
| `TokenLifecycleMonitor` | Monitors expiry, revocation, scope changes |

### Zero-Custody Guarantee

- Platform **never holds funds** — only reads balance/transaction data
- `ZeroCustodyComplianceGuard.validateNoClientFundsHeld()` enforces this
- All tokens are encrypted at rest via `TokenVaultService`

---

## 2. PANÉTA — SEND MONEY (Local/Same Currency)

### Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                    LOCAL SEND MONEY FLOW                             │
└─────────────────────────────────────────────────────────────────────┘
     User                    PANÉTA                    Issuer Bank
       │                       │                            │
       │──Create Transaction──▶│                            │
       │                       │  ComplianceEngine          │
       │                       │  ├─KYC Check               │
       │                       │  ├─Sanctions Screen        │
       │                       │  └─Transaction Limits      │
       │                       │                            │
       │                       │  DigitalInstructionSigning │
       │                       │  ├─generateInstructionHash │
       │                       │  ├─signInstruction         │
       │                       │  └─sealInstructionRecord   │
       │                       │                            │
       │                       │  PaymentInstruction        │
       │                       │  └─dispatch()─────────────▶│
       │                       │                            │
       │                       │◀──Execution Confirmation───│
       │                       │                            │
       │                       │  ImmutableAuditSealService │
       │                       │  └─sealAuditBlock()        │
       │◀──Confirmation────────│                            │
```

### Key Services

| Service | Responsibility |
|---------|----------------|
| `TransactionOrchestrationEngine` | Creates TransactionIntent |
| `DigitalInstructionSigningService` | Signs instructions with immutable hash |
| `CompositeInstructionBuilder` | Builds payment instructions |
| `FeeEngine` | Calculates platform fees |
| `ImmutableAuditSealService` | Hash-chains audit records |

### Instruction-Only Model

- PANÉTA generates a **signed PaymentInstruction**
- Instruction sent to **issuer bank for execution**
- Platform never touches funds — purely orchestration layer

---

## 3. PANÉTA — SEND MONEY (Cross-Border with FX Auto-Conversion)

### Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                 CROSS-BORDER FX AUTO-CONVERSION                      │
└─────────────────────────────────────────────────────────────────────┘
     User              PANÉTA                FX Provider      Acquirer
       │                 │                        │               │
       │──Send USD→EUR──▶│                        │               │
       │                 │  FXDiscoveryEngine     │               │
       │                 │  └─getBestRate()──────▶│               │
       │                 │◀──FX Quote─────────────│               │
       │                 │                        │               │
       │                 │  FXNeutralityGuard     │               │
       │                 │  └─validateExternalExecutionOnly()     │
       │                 │                        │               │
       │                 │  AtomicExecutionCoordinator            │
       │                 │  ├─registerLinkedInstructions()        │
       │                 │  └─dispatchAtomicInstructions()        │
       │                 │                        │               │
       │                 │  LEG 1: Source Debit   │               │
       │                 │  ────────────────────────────────────▶│
       │                 │                        │               │
       │                 │  LEG 2: FX Conversion  │               │
       │                 │  ──────────────────────▶│               │
       │                 │◀──Converted Amount─────│               │
       │                 │                        │               │
       │                 │  LEG 3: Dest Credit    │               │
       │                 │  ────────────────────────────────────▶│
       │                 │                        │               │
       │                 │  TransactionReconciliation             │
       │                 │  └─finalizeTransaction()               │
       │◀──Complete──────│                        │               │
```

### Key Services

| Service | Responsibility |
|---------|----------------|
| `CrossBorderOrchestrationEngine` | Coordinates multi-leg execution |
| `FXDiscoveryEngine` | Discovers and caches FX rates |
| `FXRFQBroadcastService` | Broadcasts RFQ to multiple providers |
| `FXNeutralityGuard` | **Ensures external-only FX execution** |
| `AtomicExecutionCoordinator` | All-or-nothing execution |
| `ExecutionFailureHandler` | Rollback on partial failure |
| `TransactionReconciliationEngine` | Validates multi-leg reconciliation |

### FX Neutrality

- `FXNeutralityGuard.validateExternalExecutionOnly()` — Platform cannot convert FX internally
- All FX conversion via **external licensed providers**
- This ensures PANÉTA is not classified as an FX dealer

### Multi-Leg Transaction States

1. `pending` — Intent created
2. `fx_locked` — FX quote locked
3. `source_debited` — Source account debited
4. `fx_executed` — FX conversion completed
5. `destination_credited` — Funds credited to recipient
6. `completed` — All legs successful

---

## 4. PANÉTA — PAYMENT REQUEST / RECEIVE MONEY

### Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                    PAYMENT REQUEST FLOW                              │
└─────────────────────────────────────────────────────────────────────┘
   Requester             PANÉTA                    Payer
       │                   │                         │
       │──Create Request──▶│                         │
       │                   │  PaymentRequestEngine   │
       │                   │  ├─createPaymentRequest │
       │                   │  └─generateQrCodeData   │
       │◀──QR Code/Link────│                         │
       │                   │                         │
       │───────────────────│──Share Link/QR─────────▶│
       │                   │                         │
       │                   │◀──Scan & Pay────────────│
       │                   │                         │
       │                   │  fulfillPaymentRequest()│
       │                   │  ├─Validate balance     │
       │                   │  ├─Debit payer          │
       │                   │  └─Credit requester     │
       │                   │                         │
       │◀──Payment Received│                         │
       │                   │◀──Confirmation──────────│
```

### Key Services

| Service | Responsibility |
|---------|----------------|
| `PaymentRequestEngine` | Creates requests with QR codes |
| `AuditService` | Full audit trail for all requests |
| `FeeEngine` | Fee calculation on fulfillment |

### Features

- **Partial Payments**: Supported via `allow_partial` flag
- **Auto-Expiry**: Stale requests automatically expire
- **QR Code Generation**: Built-in QR data generation
- **Idempotency**: Duplicate request prevention via idempotency keys

---

## 5. PANÉTA — MERCHANT PAYMENTS (SoftPOS)

### Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                    MERCHANT SOFTPOS FLOW                             │
└─────────────────────────────────────────────────────────────────────┘
   Merchant              PANÉTA                    Customer
       │                   │                         │
       │──Register────────▶│                         │
       │                   │  MerchantOrchestration  │
       │                   │  ├─registerMerchant()   │
       │                   │  └─verifyMerchant(KYB)  │
       │◀──Approved────────│                         │
       │                   │                         │
       │──Register Device─▶│                         │
       │                   │  registerDevice()       │
       │◀──Device Active───│                         │
       │                   │                         │
       │──Generate QR──────▶│                         │
       │                   │  generatePaymentQr()    │
       │◀──QR Code─────────│                         │
       │                   │                         │
       │                   │◀──Customer Scans────────│
       │                   │                         │
       │                   │  processPayment()       │
       │                   │  ├─Debit customer       │
       │                   │  └─Credit settlement    │
       │◀──Settlement──────│                         │
       │                   │◀──Receipt───────────────│
```

### Key Services

| Service | Responsibility |
|---------|----------------|
| `MerchantOrchestrationEngine` | Merchant registration, KYB, device management |
| `MerchantDevice` | SoftPOS terminal management |
| `PaymentRequestEngine` | Payment QR generation |
| `FeeEngine` | Merchant fee calculation |

### Merchant Lifecycle

1. **Registration**: `registerMerchant()` with business details
2. **KYB Verification**: `verifyMerchant()` — Know Your Business check
3. **Settlement Account**: `setSettlementAccount()` — Link payout account
4. **Device Registration**: `registerDevice()` — Activate SoftPOS terminal
5. **Accept Payments**: `generatePaymentQr()` + `processPayment()`

---

## 6. MERCHANT PAYMENTS — CROSS-BORDER / FX AUTO-CONVERSION

### Flow Overview

Same as SoftPOS flow above, but with FX layer:

```
Customer (EUR) ──▶ FX Provider ──▶ Merchant Settlement (USD)
```

### Key Differences

- Uses `CrossBorderOrchestrationEngine` for FX leg
- `FXNeutralityGuard` ensures external FX execution
- Atomic settlement to merchant account
- Multi-currency support for international merchants

---

## 7. PANÉTA — PEER-TO-PEER FX EXCHANGE (Smart Escrow)

### Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                    P2P FX SMART ESCROW FLOW                          │
└─────────────────────────────────────────────────────────────────────┘
   User A (USD)          PANÉTA                  User B (EUR)
       │                   │                         │
       │──Create Offer────▶│                         │
       │  (Sell USD/Buy EUR)│  P2PMarketplaceEngine  │
       │                   │  └─createOffer()        │
       │                   │                         │
       │                   │◀──Create Offer──────────│
       │                   │   (Sell EUR/Buy USD)    │
       │                   │                         │
       │                   │  findMatchingOffers()   │
       │                   │  matchOffers()          │
       │                   │                         │
       │                   │  EscrowStateMachine     │
       │                   │  ├─OFFER_OPEN           │
       │                   │  ├─PENDING_VALIDATION   │
       │                   │  ├─ESCROW_ACTIVE        │
       │◀──Confirm Match───│──Confirm Match─────────▶│
       │                   │                         │
       │                   │  SmartEscrowEngine      │
       │                   │  └─executeAtomicSwap()  │
       │                   │     ├─Lock both accounts│
       │                   │     ├─Validate balances │
       │                   │     ├─Atomic swap       │
       │                   │     └─All-or-nothing    │
       │                   │                         │
       │◀──EUR Received────│──USD Received──────────▶│
```

### Key Services

| Service | Responsibility |
|---------|----------------|
| `P2PMarketplaceEngine` | Offer creation, matching, execution |
| `SmartEscrowEngine` | Atomic swap execution |
| `EscrowStateMachine` | State transitions (OPEN → MATCHED → EXECUTED) |
| `AtomicExecutionCoordinator` | Ensures all-or-nothing |

### Escrow State Machine

```
OFFER_OPEN
    │
    ▼
PENDING_VALIDATION ──▶ VALIDATION_FAILED
    │
    ▼
ESCROW_ACTIVE
    │
    ├──▶ AWAITING_CONFIRMATION
    │         │
    │         ▼
    │    DUAL_CONFIRMED ──▶ EXECUTING ──▶ COMPLETED
    │
    └──▶ TIMEOUT_EXPIRED ──▶ FUNDS_RETURNED
```

### Zero-Custody Escrow

- Escrow is **logical state machine** — actual funds remain in user's external accounts
- `ZeroCustodyComplianceGuard` validates no internal holding
- Atomic swap ensures simultaneous debit/credit

---

## 8. PANÉTA — PEER-TO-GLOBAL FX MARKETPLACE (Neutral Liquidity)

### Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│              NEUTRAL FX LIQUIDITY ORCHESTRATION                      │
└─────────────────────────────────────────────────────────────────────┘
     User              PANÉTA           Provider A    Provider B
       │                 │                   │             │
       │──Request Quote─▶│                   │             │
       │                 │  FXRFQBroadcastService          │
       │                 │  └─broadcastRFQ()───▶│             │
       │                 │  └─broadcastRFQ()──────────────▶│
       │                 │                   │             │
       │                 │◀──Quote───────────│             │
       │                 │◀──Quote────────────────────────│
       │                 │                   │             │
       │                 │  rankByNetOutcome()             │
       │                 │  └─Best rate after fees         │
       │◀──Best Quote────│                   │             │
       │                 │                   │             │
       │──Accept Quote──▶│                   │             │
       │                 │  FXNeutralityGuard              │
       │                 │  └─enforceProviderSettlementPath│
       │                 │                   │             │
       │                 │──Execute via Provider──────────▶│
       │◀──Confirmation──│                   │             │
```

### Key Services

| Service | Responsibility |
|---------|----------------|
| `FXRFQBroadcastService` | Broadcasts RFQ to multiple providers simultaneously |
| `FXProviderScoringService` | Scores providers by reliability, speed, spread |
| `BestExecutionEngine` | Selects optimal execution path |
| `LiquidityRoutingEngine` | Routes through liquidity pools |
| `FXNeutralityGuard` | Ensures external-only execution |

### RFQ Broadcasting

1. **Broadcast**: Send RFQ to all registered FX providers
2. **Collect**: Gather quotes with timeout handling
3. **Rank**: Score quotes by net outcome (rate - fees)
4. **Select**: Choose best execution path
5. **Execute**: Route through selected provider

### FX Neutrality

- PANÉTA is **neutral orchestrator** — never holds FX positions
- All execution through **external licensed providers**
- `FXNeutralityGuard.rejectInternalConversionAttempt()` blocks internal FX

---

## Cross-Cutting Compliance Services

### Core Compliance Stack

| Service | Purpose |
|---------|---------|
| `DigitalInstructionSigningService` | Every instruction digitally signed with immutable hash |
| `ImmutableAuditSealService` | Hash-chained audit trail for regulatory proof |
| `ZeroCustodyComplianceGuard` | Validates platform never holds client funds |
| `FXNeutralityGuard` | Prevents platform from being classified as FX dealer |
| `FeeSettlementValidator` | Fees collected at execution layer, not held |
| `AtomicExecutionCoordinator` | All-or-nothing for multi-leg transactions |

### Additional Compliance Services

| Service | Purpose |
|---------|---------|
| `ConsentScopeGuard` | Enforces read-only consent scopes |
| `TokenLifecycleMonitor` | Monitors token expiry and revocation |
| `InstructionImmutabilityGuard` | Prevents post-signature modification |
| `DisclosureAcceptanceRegistry` | Tracks user acceptance of disclaimers |
| `WealthAdvisoryBoundaryGuard` | Enforces no-advisory boundaries |
| `RegulatorAccessGateway` | Provides read-only regulator access |

### Audit Trail

Every operation generates an immutable audit record:

```php
ImmutableAuditSealService::sealAuditBlock([
    'action' => 'cross_border_completed',
    'entity_type' => 'CrossBorderTransactionIntent',
    'entity_id' => $intent->id,
    'user_id' => $user->id,
    'metadata' => [...],
    'timestamp' => now(),
    'previous_hash' => $lastBlock->hash,
    'hash' => hash('sha256', $payload),
]);
```

---

## Summary

| Feature | Key Principle | Primary Service |
|---------|---------------|-----------------|
| Account Aggregation | Zero-Custody, Read-Only | `AggregationEngine` |
| Local Send Money | Instruction-Only | `TransactionOrchestrationEngine` |
| Cross-Border Send | Atomic Multi-Leg + FX Neutral | `CrossBorderOrchestrationEngine` |
| Payment Requests | QR-Based P2P | `PaymentRequestEngine` |
| Merchant SoftPOS | KYB + Device Management | `MerchantOrchestrationEngine` |
| P2P FX Exchange | Smart Escrow | `P2PMarketplaceEngine` |
| Global FX Marketplace | Neutral Liquidity | `FXRFQBroadcastService` |

All flows adhere to:
- **Zero-Custody**: Platform never holds client funds
- **Atomic Execution**: All-or-nothing multi-leg transactions
- **FX Neutrality**: External-only FX execution
- **Immutable Audit**: Hash-chained audit trail
- **Digital Signing**: Every instruction cryptographically signed
