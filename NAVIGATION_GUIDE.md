# PANÉTA System Navigation Guide

**Version:** 1.0  
**Last Updated:** February 2026

---

## Table of Contents

1. [Overview](#overview)
2. [User Roles](#user-roles)
3. [Regular User Navigation](#regular-user-navigation)
4. [Admin/Regulator Navigation](#adminregulator-navigation)
5. [API Access](#api-access)
6. [Quick Reference](#quick-reference)

---

## Overview

PANÉTA is a **zero-custody financial orchestration platform** that enables users to perform various financial operations without the platform holding their funds. The system supports three primary user types:

- **Regular Users** - End users performing financial transactions
- **Admins** - System administrators with oversight capabilities
- **Regulators** - Read-only access to audit trails and compliance data

---

## User Roles

### Regular User
- Full access to personal financial features
- Can link accounts, send money, receive payments, trade FX
- Merchant capabilities (if registered)
- Personal audit log access

### Admin/Regulator
- Read-only oversight of all system activities
- Access to comprehensive audit trails
- User management visibility
- Transaction monitoring
- System-wide statistics

---

## Regular User Navigation

### 1. Getting Started

#### **Login & Authentication**
- **URL:** `http://paneta.test/login`
- **Post-Login Redirect:** `http://paneta.test/paneta`
- **Features:** Email/password authentication via Laravel Fortify

#### **Dashboard**
- **URL:** `http://paneta.test/paneta`
- **Route:** `paneta.dashboard`
- **Features:**
  - Total balance across all linked accounts
  - Accounts grouped by currency
  - Recent transactions (last 5)
  - Pending payment requests
  - Quick actions (Send Money, Receive Money, Link Account)

---

### 2. Account Management

#### **Linked Accounts**
- **URL:** `http://paneta.test/paneta/accounts`
- **Route:** `paneta.accounts.index`
- **Features:**
  - View all linked bank/financial accounts
  - Link new accounts (select bank + enter account number)
  - Revoke account consent
  - Refresh account data
  - View account balances and status

**Actions:**
- **Link Account:** `POST /paneta/accounts`
- **Revoke Consent:** `POST /paneta/accounts/{id}/revoke`
- **Refresh Data:** `POST /paneta/accounts/{id}/refresh`

---

### 3. Send Money

#### **Transaction Creation**
- **URL:** `http://paneta.test/paneta/transactions/create`
- **Route:** `paneta.transactions.create`
- **Features:**
  - Select source account (issuer)
  - Enter destination identifier (account number/phone)
  - Specify amount and currency
  - Add description/reference
  - Pre-execution compliance checks (KYC, limits, balance)

**Flow:**
1. Navigate to "Send Money" from dashboard
2. Select source account
3. Enter destination identifier
4. Enter amount
5. Review fees and total
6. Confirm transaction
7. View execution status

**Actions:**
- **Create Transaction:** `POST /paneta/transactions`
- **View Transaction:** `GET /paneta/transactions/{id}`

#### **Transaction History**
- **URL:** `http://paneta.test/paneta/transactions`
- **Route:** `paneta.transactions.index`
- **Features:**
  - View all transaction intents
  - Filter by status (pending, executed, failed)
  - View transaction details
  - Track multi-leg executions

---

### 4. Receive Money

#### **Payment Requests**
- **URL:** `http://paneta.test/paneta/payment-requests`
- **Route:** `paneta.payment-requests.index`
- **Features:**
  - Create payment requests with QR codes
  - Set amount, currency, description
  - Allow partial payments (optional)
  - Set expiry time
  - View pending/fulfilled requests
  - Cancel active requests

**Flow:**
1. Navigate to "Payment Requests"
2. Click "Create Payment Request"
3. Select destination account
4. Enter amount and details
5. Generate QR code
6. Share QR code with payer
7. Track payment status

**Actions:**
- **Create Request:** `POST /paneta/payment-requests`
- **Cancel Request:** `POST /paneta/payment-requests/{id}/cancel`
- **View Request:** `GET /paneta/payment-requests/{id}`

---

### 5. Cross-Border & FX

#### **Currency Exchange**
- **URL:** `http://paneta.test/paneta/currency-exchange`
- **Route:** `paneta.currency-exchange.index`
- **Features:**
  - View FX providers and rates
  - Get real-time quotes
  - Compare provider rates
  - Execute cross-border transactions
  - Multi-leg execution (debit → FX → credit)

**Flow:**
1. Navigate to "Currency Exchange"
2. Select source and destination currencies
3. Enter amount
4. View available providers and rates
5. Select best rate
6. Execute FX transaction

**Actions:**
- **Get Quote:** `POST /paneta/currency-exchange/quote`

---

### 6. P2P FX Marketplace

#### **P2P Escrow**
- **URL:** `http://paneta.test/paneta/p2p-escrow`
- **Route:** `paneta.p2p-escrow.index`
- **Features:**
  - Create FX offers (sell/buy currencies)
  - Set custom exchange rates
  - Specify min/max amounts
  - Find matching counter-offers
  - Atomic swap execution
  - View offer history

**Flow:**
1. Navigate to "P2P FX Escrow"
2. Create offer (specify currencies, rate, amount)
3. Wait for matches OR find existing offers
4. Accept matching offer
5. Atomic swap executes automatically
6. Funds exchanged simultaneously

**Actions:**
- **Create Offer:** `POST /paneta/p2p-escrow/offers`
- **Cancel Offer:** `POST /paneta/p2p-escrow/offers/{id}/cancel`
- **Find Matches:** `GET /paneta/p2p-escrow/offers/{id}/matches`
- **Accept Match:** `POST /paneta/p2p-escrow/offers/{id}/accept/{counterOfferId}`

#### **FX Marketplace**
- **URL:** `http://paneta.test/paneta/fx-marketplace`
- **Route:** `paneta.fx-marketplace.index`
- **Features:**
  - View live order book for currency pairs
  - Take existing offers instantly
  - Multi-provider liquidity aggregation
  - Best execution routing

**Flow:**
1. Navigate to "FX Marketplace"
2. Select currency pair
3. View order book
4. Take offer or create counter-offer
5. Instant execution

**Actions:**
- **Get Order Book:** `GET /paneta/fx-marketplace/order-book`
- **Take Offer:** `POST /paneta/fx-marketplace/offers/{id}/take`

---

### 7. Merchant Services (SoftPOS)

#### **Merchant Dashboard**
- **URL:** `http://paneta.test/paneta/merchant`
- **Route:** `paneta.merchant.index`
- **Features:**
  - Register as merchant (KYB verification)
  - Manage devices (SoftPOS terminals)
  - Generate payment QR codes
  - Set settlement account
  - View merchant statistics
  - Track merchant fees (0.99% default)

**Flow:**
1. Navigate to "Merchant"
2. Register business (if not registered)
3. Set settlement account
4. Register devices
5. Generate payment QR codes
6. Accept customer payments
7. Receive net amount (after fees)

**Actions:**
- **Register Merchant:** `POST /paneta/merchant/register`
- **Set Settlement Account:** `POST /paneta/merchant/{id}/settlement`
- **Register Device:** `POST /paneta/merchant/{id}/devices`
- **Generate QR:** `POST /paneta/merchant/{id}/qr`
- **Deactivate Device:** `POST /paneta/merchant/{id}/devices/{deviceId}/deactivate`

---

### 8. Wealth Management

#### **Wealth Dashboard**
- **URL:** `http://paneta.test/paneta/wealth`
- **Route:** `paneta.wealth.index`
- **Features:**
  - Aggregate holdings across institutions (read-only)
  - Multi-asset class support (equities, ETFs, bonds, crypto)
  - Portfolio analytics (TWR, IRR, volatility)
  - Asset allocation breakdown
  - Currency exposure analysis
  - Sector and geographic exposure
  - Performance tracking

**View-Only Features:**
- Total portfolio value
- Risk score (1-10)
- Time-Weighted Return (TWR)
- Internal Rate of Return (IRR)
- Volatility percentage
- Asset allocation charts
- Performance history

---

### 9. Personal Audit Logs

#### **Audit Trail**
- **URL:** `http://paneta.test/paneta/audit-logs`
- **Route:** `paneta.audit-logs.index`
- **Features:**
  - View all personal actions
  - Filter by action type
  - Search by date range
  - Export audit data

**Logged Actions:**
- Account linking/revocation
- Transaction creation/execution
- Payment request creation/fulfillment
- FX offer creation/execution
- Merchant activities

---

## Admin/Regulator Navigation

### Access Requirements
- **Middleware:** `EnsureUserIsAdmin`
- **User Field:** `is_admin = true`
- **Access Level:** Read-only oversight

---

### 1. Admin Dashboard

#### **Overview**
- **URL:** `http://paneta.test/paneta/admin`
- **Route:** `paneta.admin.dashboard`
- **Features:**
  - System-wide statistics
  - Total users (verified vs unverified)
  - Total transactions (executed, failed, pending)
  - Transaction volume (total and today)
  - Recent transactions (last 10)

**Statistics:**
- Total Users
- Verified Users (KYC completed)
- Total Transactions
- Executed Transactions
- Failed Transactions
- Pending Transactions
- Total Volume
- Today's Volume

---

### 2. Transaction Monitoring

#### **All Transactions**
- **URL:** `http://paneta.test/paneta/admin/transactions`
- **Route:** `paneta.admin.transactions`
- **Features:**
  - View all system transactions
  - Filter by status (pending, executed, failed)
  - Filter by user
  - Pagination (50 per page)
  - View transaction details
  - See payment instructions
  - Track multi-leg executions

**Filters:**
- Status: `pending`, `executing`, `executed`, `failed`, `cancelled`
- User ID
- Date range

**Data Visible:**
- Transaction ID
- User
- Source account
- Destination identifier
- Amount and currency
- Status
- Created/updated timestamps
- Failure reasons (if failed)

---

### 3. Comprehensive Audit Logs

#### **System Audit Trail**
- **URL:** `http://paneta.test/paneta/admin/audit-logs`
- **Route:** `paneta.admin.audit-logs`
- **Features:**
  - View all system audit logs
  - Filter by action type
  - Filter by user
  - Pagination (100 per page)
  - Export capabilities

**Logged Actions:**
- `account_linked` - User linked new account
- `account_revoked` - User revoked account consent
- `transaction_intent_created` - Transaction initiated
- `transaction_executed` - Transaction completed
- `transaction_failed` - Transaction failed
- `payment_request_created` - Payment request generated
- `payment_request_fulfilled` - Payment request paid
- `fx_offer_created` - FX offer created
- `fx_offers_matched` - FX offers matched
- `fx_offer_executed` - FX swap executed
- `merchant_registered` - Merchant registered
- `merchant_payment_completed` - Merchant payment processed

**Data Visible:**
- Timestamp
- User
- Action type
- Entity type and ID
- Metadata (JSON)
- IP address
- User agent

---

### 4. User Management

#### **User Overview**
- **URL:** `http://paneta.test/paneta/admin/users`
- **Route:** `paneta.admin.users`
- **Features:**
  - View all users
  - User statistics (linked accounts, transactions)
  - KYC status
  - Risk tier
  - Pagination (50 per page)

**User Data:**
- User ID
- Name and email
- KYC status (pending, under_review, verified, rejected)
- Risk tier (low, medium, high)
- Linked accounts count
- Transaction count
- Registration date
- Last activity

---

### 5. Regulatory Compliance

#### **Compliance Features**
- **Read-Only Access:** Admins/Regulators cannot modify data
- **Audit Trail:** Complete immutable audit log
- **Transaction Transparency:** Full visibility into all transactions
- **User Activity:** Track all user actions
- **Compliance Checks:** View KYC, AML, daily limits

**Regulatory Visibility:**
- Pre-execution compliance checks
- KYC verification status
- Daily transaction limits
- Balance verification
- Account status checks
- Fee transparency
- FX rate disclosure
- Multi-leg transaction tracking

---

## API Access

### Authentication
- **Method:** Bearer Token (Sanctum)
- **Endpoint:** `POST /api/auth/login`

### Admin API Routes

#### **Statistics**
```
GET /api/admin/stats
```
Returns system-wide statistics.

#### **Transactions**
```
GET /api/admin/transactions?status={status}&user_id={userId}
```
Returns paginated transactions with filters.

#### **Audit Logs**
```
GET /api/admin/audit-logs?action={action}&user_id={userId}
```
Returns paginated audit logs with filters.

#### **Users**
```
GET /api/admin/users
```
Returns paginated user list with statistics.

---

## Quick Reference

### User Journey Map

```
Login → Dashboard → Choose Action:
├── Send Money → Select Account → Enter Details → Execute
├── Receive Money → Create Payment Request → Share QR → Track
├── Link Account → Select Bank → Enter Account Number → Consent
├── FX Exchange → Get Quote → Select Provider → Execute
├── P2P FX → Create Offer → Match → Atomic Swap
├── Merchant → Register → Setup Device → Generate QR → Accept Payments
└── Wealth → View Holdings → Analytics → Performance
```

### Admin Journey Map

```
Admin Login → Admin Dashboard → Choose View:
├── Transactions → Filter → View Details
├── Audit Logs → Filter by Action → Export
├── Users → View Statistics → KYC Status
└── System Stats → Monitor Volume → Track Compliance
```

---

## URL Structure

### User URLs
| Feature | URL | Route Name |
|---------|-----|------------|
| Dashboard | `/paneta` | `paneta.dashboard` |
| Linked Accounts | `/paneta/accounts` | `paneta.accounts.index` |
| Send Money | `/paneta/transactions/create` | `paneta.transactions.create` |
| Transaction History | `/paneta/transactions` | `paneta.transactions.index` |
| Payment Requests | `/paneta/payment-requests` | `paneta.payment-requests.index` |
| Currency Exchange | `/paneta/currency-exchange` | `paneta.currency-exchange.index` |
| P2P Escrow | `/paneta/p2p-escrow` | `paneta.p2p-escrow.index` |
| FX Marketplace | `/paneta/fx-marketplace` | `paneta.fx-marketplace.index` |
| Merchant | `/paneta/merchant` | `paneta.merchant.index` |
| Wealth | `/paneta/wealth` | `paneta.wealth.index` |
| Audit Logs | `/paneta/audit-logs` | `paneta.audit-logs.index` |

### Admin URLs
| Feature | URL | Route Name |
|---------|-----|------------|
| Admin Dashboard | `/paneta/admin` | `paneta.admin.dashboard` |
| All Transactions | `/paneta/admin/transactions` | `paneta.admin.transactions` |
| System Audit Logs | `/paneta/admin/audit-logs` | `paneta.admin.audit-logs` |
| User Management | `/paneta/admin/users` | `paneta.admin.users` |

---

## Key Features by User Type

### Regular User Features
✅ Link multiple bank/financial accounts  
✅ Send money using destination identifiers  
✅ Receive money via payment requests with QR codes  
✅ Cross-border transactions with FX conversion  
✅ P2P FX marketplace with atomic swaps  
✅ Instant liquidity FX marketplace  
✅ Merchant services (SoftPOS)  
✅ Wealth aggregation (read-only)  
✅ Personal audit trail  
✅ Multi-currency support  
✅ Fee transparency  

### Admin/Regulator Features
✅ System-wide transaction monitoring  
✅ Comprehensive audit trail access  
✅ User management visibility  
✅ KYC/compliance status tracking  
✅ Transaction volume analytics  
✅ Read-only access (no modifications)  
✅ Export capabilities  
✅ Filter and search functionality  
✅ Real-time statistics  

---

## Security & Compliance

### User Security
- **Authentication:** Laravel Fortify (email/password)
- **Session Management:** Secure session handling
- **Consent Management:** Revocable account consents
- **Zero-Custody:** Platform never holds user funds
- **Audit Trail:** All actions logged

### Admin Security
- **Role-Based Access:** Middleware-enforced admin access
- **Read-Only:** No data modification capabilities
- **Audit Logging:** All admin actions logged
- **IP Tracking:** Admin access tracked

### Compliance
- **KYC Verification:** User identity verification
- **Daily Limits:** Transaction limit enforcement
- **Balance Checks:** Pre-execution balance verification
- **AML Checks:** Basic anti-money laundering checks
- **Audit Trail:** Immutable audit log
- **Fee Transparency:** All fees disclosed upfront
- **FX Rate Disclosure:** Exchange rates clearly shown

---

## Support & Documentation

### For Users
- Dashboard quick actions guide
- In-app tooltips and help text
- Transaction status explanations
- Fee calculators

### For Admins/Regulators
- Audit log action reference
- Transaction status definitions
- Compliance check explanations
- Export formats and procedures

---

## Appendix: Transaction Flow Examples

### Example 1: Send Money (Local)
1. User logs in → Dashboard
2. Click "Send Money"
3. Select source account (e.g., Bank of X - USD)
4. Enter destination identifier (e.g., account number)
5. Enter amount (e.g., $100)
6. System shows fee (e.g., $0.50) and total ($100.50)
7. User confirms
8. Compliance checks (KYC, balance, limits)
9. Transaction executed
10. Source debited, destination credited
11. Confirmation shown

### Example 2: Receive Money (Payment Request)
1. User logs in → Dashboard
2. Click "Receive Money" or navigate to Payment Requests
3. Click "Create Payment Request"
4. Select destination account
5. Enter amount (e.g., $50)
6. Add description (e.g., "Lunch payment")
7. Generate QR code
8. Share QR with payer
9. Payer scans QR and pays
10. Payment request fulfilled
11. User receives notification

### Example 3: P2P FX Swap
1. User A creates offer: Sell 1000 USD, Buy ZAR at rate 18.5
2. User B creates counter-offer: Sell 18500 ZAR, Buy USD at rate 0.054
3. System matches offers
4. Atomic swap executes:
   - User A: -1000 USD, +18500 ZAR
   - User B: +1000 USD, -18500 ZAR
5. Both users receive confirmation

### Example 4: Merchant Payment
1. Merchant generates QR code for $25
2. Customer scans QR
3. Customer selects payment account
4. Customer confirms payment
5. System debits customer $25
6. System calculates merchant fee (0.99% = $0.25)
7. System credits merchant settlement account $24.75
8. Both parties receive confirmation

---

**End of Navigation Guide**
