# Panéta Capital - System Features Documentation

## Overview

Panéta Capital is a comprehensive fintech platform for cross-border payments, currency exchange, wealth management, and financial account aggregation. The system provides secure, compliant financial services with full audit trails and administrative controls.

---

## User Roles

| Role | Description |
|------|-------------|
| **User** | Standard user with access to personal financial features |
| **Admin** | Full administrative access to platform management |
| **Regulator** | Read-only access for compliance and regulatory oversight |

---

## Core Features

### 1. Authentication & Security

| Feature | Description |
|---------|-------------|
| **User Registration** | Secure account creation with email verification |
| **Login** | Email/password authentication with remember me option |
| **Two-Factor Authentication** | Optional 2FA for enhanced account security |
| **Password Reset** | Secure password recovery via email |
| **Session Management** | Active session tracking and logout capabilities |

---

### 2. Dashboard

| Feature | Description |
|---------|-------------|
| **Overview** | Consolidated view of all linked accounts and balances |
| **Recent Transactions** | Quick view of latest transaction activity |
| **Account Summary** | Total balance across all linked accounts |
| **Quick Actions** | Fast access to send money and link accounts |

---

### 3. Linked Accounts

| Feature | Description |
|---------|-------------|
| **Link Account** | Connect external bank accounts and financial institutions |
| **View Accounts** | List all linked accounts with status and balance |
| **Revoke Consent** | Remove access to a linked account |
| **Refresh Consent** | Renew account access permissions |
| **Multi-Currency Support** | Link accounts in different currencies (USD, EUR, GBP, ZAR, ZWL, KES, NGN) |

**Supported Institution Types:**
- Banks
- Payment Providers
- FX Providers
- Brokers
- Custodians
- Remittance Services

---

### 4. Send Money (Transactions)

| Feature | Description |
|---------|-------------|
| **Create Transaction** | Initiate money transfers to recipients |
| **Select Source Account** | Choose from linked accounts to fund transfer |
| **Recipient Identification** | Enter recipient identifier (account number, email, phone) |
| **Amount Entry** | Specify transfer amount with currency |
| **Transaction Confirmation** | Review and confirm before execution |
| **Transaction Status** | Track pending, executed, and failed transactions |
| **Transaction History** | View all past transactions with details |
| **Transaction Details** | Detailed view of individual transactions |

**Transaction Statuses:**
- `pending` - Awaiting processing
- `executed` - Successfully completed
- `failed` - Transaction failed
- `cancelled` - User cancelled

---

### 5. Currency Exchange

| Feature | Description |
|---------|-------------|
| **Get FX Quotes** | Request exchange rate quotes from multiple providers |
| **Compare Rates** | View quotes from different FX providers side-by-side |
| **Rate Details** | See exchange rate, spread, fees, and estimated amount |
| **Quote Expiry** | Quotes valid for 5 minutes before expiring |
| **Best Execution** | System finds best available rates |
| **Recent Quotes** | View history of requested quotes |

**Supported Currencies:**
- USD (US Dollar)
- EUR (Euro)
- GBP (British Pound)
- ZAR (South African Rand)
- ZWL (Zimbabwe Dollar)
- KES (Kenyan Shilling)
- NGN (Nigerian Naira)

---

### 6. Wealth Management (Read-Only)

| Feature | Description |
|---------|-------------|
| **Portfolio Overview** | Consolidated view of investment holdings |
| **Asset Allocation** | Breakdown by asset class (Equities, ETFs, Bonds, Crypto) |
| **Holdings List** | Detailed list of individual holdings with prices |
| **Performance Metrics** | TWR, IRR, and period returns (1M, 3M, YTD, 1Y) |
| **Risk Score** | Portfolio risk assessment |
| **Volatility** | Portfolio volatility metrics |
| **Sector Exposure** | Allocation by sector (Technology, Diversified, Fixed Income, Digital Assets) |
| **Geographic Exposure** | Regional allocation (North America, Global, Africa) |
| **Currency Exposure** | Currency breakdown of portfolio |

---

### 7. Audit Logs

| Feature | Description |
|---------|-------------|
| **Activity Log** | Record of all user actions on the platform |
| **Action Types** | Login, account linked/revoked, transactions, consent changes |
| **Timestamps** | Precise date/time of each action |
| **IP Tracking** | Source IP address logging |
| **Entity References** | Links to related accounts/transactions |

---

## Administrative Features

### 8. Admin Dashboard

| Feature | Description |
|---------|-------------|
| **User Statistics** | Total users, verified users count |
| **Transaction Statistics** | Total, executed, failed, pending counts |
| **Volume Metrics** | Total and daily transaction volume |
| **Recent Transactions** | Platform-wide recent activity |

---

### 9. User Management (Admin)

| Feature | Description |
|---------|-------------|
| **User List** | View all registered users |
| **KYC Status** | User verification status (pending, verified, rejected) |
| **Risk Tier** | User risk classification (low, medium, high) |
| **Linked Accounts Count** | Number of accounts per user |
| **Transaction Count** | Number of transactions per user |

---

### 10. Transaction Monitoring (Admin)

| Feature | Description |
|---------|-------------|
| **All Transactions** | View all platform transactions |
| **Status Filtering** | Filter by transaction status |
| **User Filtering** | Filter by specific user |
| **Transaction Details** | Full transaction information including payment instructions |

---

### 11. Audit Log Management (Admin)

| Feature | Description |
|---------|-------------|
| **All Audit Logs** | Platform-wide audit trail |
| **Action Filtering** | Filter by action type |
| **User Filtering** | Filter by user |
| **Distinct Actions** | View all unique action types |

---

## Backend Services & Engines

### Core Services

| Service | Description |
|---------|-------------|
| **OrchestrationEngine** | Coordinates transaction flow and dashboard data |
| **ConsentService** | Manages account linking and consent lifecycle |
| **AuditService** | Records all auditable actions |
| **SecurityService** | Handles encryption, authentication, and security |
| **NotificationService** | Sends email and in-app notifications |

### Financial Services

| Service | Description |
|---------|-------------|
| **FXDiscoveryEngine** | Discovers available FX providers and rates |
| **FXCorridorPolicyEngine** | Manages FX corridor rules and policies |
| **FXProviderScoringService** | Scores and ranks FX providers |
| **FXQuoteNormaliser** | Standardizes quotes from different providers |
| **BestExecutionEngine** | Finds optimal execution path for transactions |
| **LiquidityRoutingEngine** | Routes transactions through liquidity pools |
| **FeeEngine** | Calculates platform and transaction fees |

### Compliance & Risk

| Service | Description |
|---------|-------------|
| **ComplianceEngine** | Ensures regulatory compliance |
| **RiskEngine** | Assesses transaction and user risk |
| **RiskTierAssignmentService** | Assigns risk tiers to users |
| **SanctionsScreeningService** | Screens against sanctions lists |
| **JurisdictionPolicyEngine** | Enforces jurisdiction-specific rules |
| **SARReportGenerator** | Generates Suspicious Activity Reports |

### Data & Analytics

| Service | Description |
|---------|-------------|
| **AggregationEngine** | Aggregates data from multiple sources |
| **DataNormalisationEngine** | Normalizes data formats |
| **AssetNormalisationEngine** | Standardizes asset information |
| **WealthAnalyticsEngine** | Calculates portfolio analytics |
| **FXVolumeMetricsService** | Tracks FX volume metrics |

### Operations

| Service | Description |
|---------|-------------|
| **SettlementBatchService** | Manages settlement batches |
| **ReconciliationEngine** | Reconciles transactions |
| **DisputeEngine** | Handles transaction disputes |
| **TreasuryLedgerService** | Manages platform treasury |
| **KeyManagementService** | Manages encryption keys |
| **TokenVaultService** | Securely stores sensitive tokens |

### Additional Services

| Service | Description |
|---------|-------------|
| **IdentityResolutionEngine** | Resolves and verifies user identities |
| **OnboardingStateMachine** | Manages user onboarding flow |
| **SubscriptionEngine** | Handles subscription plans and billing |
| **PaymentRequestEngine** | Processes payment requests |
| **MerchantOrchestrationEngine** | Manages merchant integrations |
| **P2PMarketplaceEngine** | Peer-to-peer marketplace functionality |
| **SmartEscrowEngine** | Manages escrow transactions |
| **HealthCheckService** | System health monitoring |
| **PrivacyComplianceService** | GDPR and privacy compliance |

### Zero-Custody Compliance Services (NEW)

| Service | Description |
|---------|-------------|
| **DigitalInstructionSigningService** | Mandatory digital signing of payment instructions with immutable IDs and hash verification |
| **ImmutableAuditSealService** | Hash-chained audit records for tamper-proof logging and chain integrity verification |
| **AtomicExecutionCoordinator** | All-or-nothing execution guard for multi-leg transactions with rollback support |
| **FXNeutralityGuard** | Prevents internal FX conversion, blocks internal netting, enforces external provider settlement |
| **FeeSettlementValidator** | Ensures fees are collected at execution layer with no internal holding |
| **EscrowStateMachine** | P2P FX escrow state management with dual-party confirmation and timeout handling |
| **ZeroCustodyComplianceGuard** | Validates platform maintains zero-custody compliance with instruction-only model |

### Token & Consent Security Services (NEW)

| Service | Description |
|---------|-------------|
| **TokenLifecycleMonitor** | Continuous token monitoring for expiry, revocation detection, and scope changes |
| **ConsentScopeGuard** | Validates read-only token scope and prevents write-scope privilege escalation |
| **InstructionImmutabilityGuard** | Enforces payment instruction immutability with version control |

### Reconciliation & Failure Handling (NEW)

| Service | Description |
|---------|-------------|
| **TransactionReconciliationEngine** | Multi-leg reconciliation with debit/credit/FX validation and amount matching |
| **ExecutionFailureHandler** | Automated reversal orchestration and atomic rollback for partial failures |
| **FXRFQBroadcastService** | Multi-provider RFQ broadcasting with response timeout handling and liquidity evaluation |

### Subscription & Access Control (NEW)

| Service | Description |
|---------|-------------|
| **SubscriptionEntitlementEnforcer** | Hard enforcement of subscription tier limits and feature gating |
| **DisclosureAcceptanceRegistry** | Tracks user acceptance of required disclaimers before feature access |

### Regulatory & Advisory Compliance (NEW)

| Service | Description |
|---------|-------------|
| **WealthAdvisoryBoundaryGuard** | Enforces no-advisory boundaries with directive language validation |
| **RegulatorAccessGateway** | Dedicated read-only regulator access with scoped visibility and mutation restrictions |

---

## Data Models

### User & Authentication
- **User** - User accounts with KYC status and risk tier
- **SecurityLog** - Security event logging

### Accounts & Institutions
- **Institution** - Banks, FX providers, brokers
- **LinkedAccount** - User's linked external accounts
- **AggregatedAccount** - Consolidated account view
- **InstitutionToken** - Secure institution API tokens

### Transactions
- **TransactionIntent** - Transaction requests
- **PaymentInstruction** - Payment execution details
- **CrossBorderTransactionIntent** - International transfers
- **AggregatedTransaction** - Transaction aggregation

### FX & Quotes
- **FxProvider** - Currency exchange providers
- **FxQuote** - Exchange rate quotes
- **FxOffer** - FX offers and deals

### Wealth & Investments
- **WealthPortfolio** - Investment portfolios
- **InvestmentAccount** - Brokerage accounts
- **HistoricalPrice** - Asset price history

### Compliance & Audit
- **AuditLog** - System audit trail
- **ComplianceCase** - Compliance investigations
- **Dispute** - Transaction disputes
- **DisputeEvidence** - Supporting documentation

### Operations
- **SettlementBatch** - Settlement processing
- **FeeLedger** - Fee tracking
- **PlatformLedger** - Platform accounting
- **CurrencyBalance** - Multi-currency balances

### Subscriptions & Merchants
- **SubscriptionPlan** - Available subscription tiers
- **Subscription** - User subscriptions
- **Merchant** - Merchant accounts
- **MerchantDevice** - POS devices
- **PaymentRequest** - Payment requests

### System
- **Notification** - User notifications
- **NotificationPreference** - Notification settings
- **SystemHealthCheck** - Health monitoring
- **SloMetric** - SLO tracking
- **DataExportRequest** - GDPR data exports
- **KeyRotation** - Encryption key rotation

---

## Security Features

| Feature | Description |
|---------|-------------|
| **Encryption at Rest** | All sensitive data encrypted |
| **Encryption in Transit** | TLS for all communications |
| **Key Rotation** | Regular encryption key rotation |
| **Audit Logging** | Comprehensive action logging |
| **IP Tracking** | Source IP logging for all actions |
| **Rate Limiting** | Protection against abuse |
| **Sanctions Screening** | Real-time sanctions checks |
| **Risk Scoring** | Automated risk assessment |

---

## API Endpoints

### Authentication
- `POST /login` - User login
- `POST /register` - User registration
- `POST /logout` - User logout
- `POST /password/reset` - Password reset

### Panéta Platform
- `GET /paneta` - Dashboard
- `GET /paneta/accounts` - Linked accounts
- `POST /paneta/accounts` - Link new account
- `POST /paneta/accounts/{id}/revoke` - Revoke account
- `POST /paneta/accounts/{id}/refresh` - Refresh consent
- `GET /paneta/transactions` - Transaction list
- `GET /paneta/transactions/create` - Send money form
- `POST /paneta/transactions` - Create transaction
- `GET /paneta/transactions/{id}` - Transaction detail
- `GET /paneta/currency-exchange` - FX dashboard
- `POST /paneta/currency-exchange/quote` - Get FX quote
- `GET /paneta/wealth` - Wealth management
- `GET /paneta/audit-logs` - User audit logs

### Admin
- `GET /paneta/admin` - Admin dashboard
- `GET /paneta/admin/transactions` - All transactions
- `GET /paneta/admin/audit-logs` - All audit logs
- `GET /paneta/admin/users` - User management

---

## Test Accounts

| Role | Email | Password |
|------|-------|----------|
| User | test@example.com | password |
| Admin | admin@example.com | password |
| Regulator | regulator@example.com | password |

---

*Document generated: February 2026*
*Version: 1.0*
