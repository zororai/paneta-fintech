# Paneta Codebase Functions Reference

This document provides a comprehensive list of all functions available in the Paneta fintech application.

---

## Table of Contents

1. [Controllers](#controllers)
   - [API Controllers](#api-controllers)
   - [Paneta Controllers](#paneta-controllers)
   - [Settings Controllers](#settings-controllers)
2. [Services](#services)
3. [Models](#models)
4. [Actions](#actions)
5. [Connectors](#connectors)
6. [Middleware](#middleware)
7. [Jobs](#jobs)
8. [Events](#events)

---

## Controllers

### API Controllers

#### AuthController (`App\Http\Controllers\Api\AuthController`)

| Function | Description |
|----------|-------------|
| `__construct(AuditService $auditService)` | Constructor with dependency injection |
| `register(Request $request): JsonResponse` | Register a new user account |
| `login(Request $request): JsonResponse` | Authenticate user and return token |
| `logout(Request $request): JsonResponse` | Revoke current access token |
| `user(Request $request): JsonResponse` | Get authenticated user details |
| `updateKycStatus(Request $request): JsonResponse` | Update user's KYC verification status |

#### AdminController (`App\Http\Controllers\Api\AdminController`)

| Function | Description |
|----------|-------------|
| `dashboard()` | Get admin dashboard statistics |
| `users()` | List all users with pagination |
| `transactions()` | List all transactions |
| `auditLogs()` | Get audit log entries |

#### AuditLogController (`App\Http\Controllers\Api\AuditLogController`)

| Function | Description |
|----------|-------------|
| `index()` | List audit log entries |
| `show($id)` | Get specific audit log entry |

#### DashboardController (`App\Http\Controllers\Api\DashboardController`)

| Function | Description |
|----------|-------------|
| `index()` | Get dashboard data for authenticated user |

#### LinkedAccountController (`App\Http\Controllers\Api\LinkedAccountController`)

| Function | Description |
|----------|-------------|
| `index()` | List user's linked accounts |
| `store(Request $request)` | Link a new account |
| `show(LinkedAccount $linkedAccount)` | Get linked account details |
| `destroy(LinkedAccount $linkedAccount)` | Remove linked account |
| `refresh(LinkedAccount $linkedAccount)` | Refresh account data |
| `balances(LinkedAccount $linkedAccount)` | Get account balances |
| `transactions(LinkedAccount $linkedAccount)` | Get account transactions |

#### TransactionController (`App\Http\Controllers\Api\TransactionController`)

| Function | Description |
|----------|-------------|
| `index()` | List user transactions |
| `store(Request $request)` | Create new transaction |
| `show(TransactionIntent $transaction)` | Get transaction details |
| `confirm(TransactionIntent $transaction)` | Confirm pending transaction |

---

### Paneta Controllers

#### DashboardController (`App\Http\Controllers\Paneta\DashboardController`)

| Function | Description |
|----------|-------------|
| `index(Request $request): Response` | Render main dashboard view |

#### TransactionController (`App\Http\Controllers\Paneta\TransactionController`)

| Function | Description |
|----------|-------------|
| `__construct(OrchestrationEngine $orchestrationEngine)` | Constructor |
| `index(Request $request): Response` | List user transactions |
| `show(Request $request, TransactionIntent $transaction): Response` | Show transaction details |
| `create(Request $request): Response` | Show send money form |
| `store(Request $request): RedirectResponse` | Create and execute transaction |

#### LinkedAccountController (`App\Http\Controllers\Paneta\LinkedAccountController`)

| Function | Description |
|----------|-------------|
| `__construct(ConsentService $consentService, AuditService $auditService)` | Constructor |
| `index(Request $request): Response` | List linked accounts |
| `store(Request $request): RedirectResponse` | Link new account |
| `revoke(Request $request, LinkedAccount $linkedAccount): RedirectResponse` | Revoke account consent |
| `refresh(Request $request, LinkedAccount $linkedAccount): RedirectResponse` | Refresh account data |

#### WealthController (`App\Http\Controllers\Paneta\WealthController`)

| Function | Description |
|----------|-------------|
| `index(Request $request): Response` | Show wealth management dashboard |
| `getLinkedInstitutions($user): array` | Get user's linked financial institutions |
| `getMockLinkedInstitutions(): array` | Get mock institutions for demo |
| `getMockHoldings(): array` | Get mock portfolio holdings |
| `getMockAnalytics(): array` | Get mock analytics data |

#### CurrencyExchangeController (`App\Http\Controllers\Paneta\CurrencyExchangeController`)

| Function | Description |
|----------|-------------|
| `index(Request $request)` | Show currency exchange page |
| `getQuote(Request $request)` | Get FX quote |
| `execute(Request $request)` | Execute currency exchange |

#### FXMarketplaceController (`App\Http\Controllers\Paneta\FXMarketplaceController`)

| Function | Description |
|----------|-------------|
| `__construct(...)` | Constructor with dependencies |
| `index(Request $request)` | Show FX marketplace |
| `getQuotes(Request $request)` | Get available FX quotes |
| `acceptQuote(Request $request, FxQuote $quote)` | Accept an FX quote |
| `getProviders()` | List FX providers |

#### P2PEscrowController (`App\Http\Controllers\Paneta\P2PEscrowController`)

| Function | Description |
|----------|-------------|
| `__construct(...)` | Constructor |
| `index(Request $request)` | Show P2P escrow dashboard |
| `createOffer(Request $request)` | Create new P2P offer |
| `cancelOffer(Request $request, FxOffer $offer)` | Cancel P2P offer |
| `findMatches(Request $request, FxOffer $offer)` | Find matching offers |
| `acceptMatch(Request $request, FxOffer $offer, FxOffer $counterOffer)` | Accept a matched offer |

#### PaymentRequestController (`App\Http\Controllers\Paneta\PaymentRequestController`)

| Function | Description |
|----------|-------------|
| `__construct(...)` | Constructor |
| `index(Request $request)` | List payment requests |
| `store(Request $request)` | Create payment request |
| `cancel(Request $request, PaymentRequest $paymentRequest)` | Cancel payment request |
| `show(Request $request, PaymentRequest $paymentRequest)` | Show payment request details |

#### MerchantController (`App\Http\Controllers\Paneta\MerchantController`)

| Function | Description |
|----------|-------------|
| `__construct(...)` | Constructor |
| `index(Request $request)` | Show merchant dashboard |
| `register(Request $request)` | Register as merchant |
| `setSettlementAccount(Request $request, Merchant $merchant)` | Set settlement account |
| `registerDevice(Request $request, Merchant $merchant)` | Register POS device |
| `deactivateDevice(Request $request, Merchant $merchant, MerchantDevice $device)` | Deactivate device |
| `generateQr(Request $request, Merchant $merchant)` | Generate payment QR code |

#### AdminController (`App\Http\Controllers\Paneta\AdminController`)

| Function | Description |
|----------|-------------|
| `index(Request $request)` | Admin dashboard |
| `users(Request $request)` | Manage users |
| `transactions(Request $request)` | View all transactions |
| `compliance(Request $request)` | Compliance management |

#### AuditLogController (`App\Http\Controllers\Paneta\AuditLogController`)

| Function | Description |
|----------|-------------|
| `index(Request $request)` | View audit logs |

#### DemoController (`App\Http\Controllers\Paneta\DemoController`)

| Function | Description |
|----------|-------------|
| `index(Request $request)` | Demo scenarios page |
| `runScenario(Request $request)` | Execute demo scenario |

---

### Settings Controllers

#### ProfileController (`App\Http\Controllers\Settings\ProfileController`)

| Function | Description |
|----------|-------------|
| `edit(Request $request): Response` | Show profile edit form |
| `update(ProfileUpdateRequest $request): RedirectResponse` | Update profile information |
| `destroy(ProfileDeleteRequest $request): RedirectResponse` | Delete user account |

#### PasswordController (`App\Http\Controllers\Settings\PasswordController`)

| Function | Description |
|----------|-------------|
| `edit(): Response` | Show password change form |
| `update(PasswordUpdateRequest $request): RedirectResponse` | Update password |

#### TwoFactorAuthenticationController (`App\Http\Controllers\Settings\TwoFactorAuthenticationController`)

| Function | Description |
|----------|-------------|
| `show(TwoFactorAuthenticationRequest $request): Response` | Show 2FA settings |

---

## Services

### AuditService (`App\Services\AuditService`)

| Function | Description |
|----------|-------------|
| `__construct()` | Constructor |
| `log(...)` | Create audit log entry |
| `logUserRegistered(User $user)` | Log user registration |
| `logUserLogin(User $user)` | Log user login |
| `logKycStatusChanged(User $user, $oldStatus, $newStatus)` | Log KYC status change |
| `logTransactionCreated(...)` | Log transaction creation |
| `logTransactionExecuted(...)` | Log transaction execution |
| `logConsentGranted(...)` | Log consent grant |
| `logConsentRevoked(...)` | Log consent revocation |
| `logSuspiciousActivity(...)` | Log suspicious activity |
| `getLogsForUser(User $user)` | Get user's audit logs |

### OrchestrationEngine (`App\Services\OrchestrationEngine`)

| Function | Description |
|----------|-------------|
| `__construct(...)` | Constructor with dependencies |
| `createTransactionIntent(...)` | Create new transaction intent |
| `confirmAndExecute(TransactionIntent $intent)` | Confirm and execute transaction |
| `validateTransaction(...)` | Validate transaction parameters |
| `routeTransaction(...)` | Route transaction to appropriate rail |

### TreasuryLedgerService (`App\Services\TreasuryLedgerService`)

| Function | Description |
|----------|-------------|
| `__construct(AuditService $auditService)` | Constructor |
| `recordFeeCollection(...)` | Record fee collection |
| `recordRefund(...)` | Record refund |
| `recordAdjustment(...)` | Record ledger adjustment |
| `recordWriteOff(...)` | Record write-off |
| `getCurrencyBalances()` | Get all currency balances |
| `getCurrencyBalance(string $currency)` | Get specific currency balance |
| `getTotalNetPosition()` | Get total net position |
| `getLedgerEntries(...)` | Get ledger entries with filters |
| `getDailyRevenue(string $currency, int $days)` | Get daily revenue |
| `getRevenueByType(...)` | Get revenue by type |
| `reconcileLedgerWithFeeLedger()` | Reconcile ledgers |

### WealthAnalyticsEngine (`App\Services\WealthAnalyticsEngine`)

| Function | Description |
|----------|-------------|
| `__construct(RiskEngine, AggregationEngine)` | Constructor |
| `calculatePortfolio(User $user)` | Calculate user portfolio |
| `calculateTotalValue(Collection $accounts, string $baseCurrency)` | Calculate total value |
| `calculateCurrencyAllocation(...)` | Calculate currency allocation |
| `calculateAssetAllocation(...)` | Calculate asset allocation |
| `convertToBaseCurrency(...)` | Convert currency |
| `getPortfolioSummary(User $user)` | Get portfolio summary |
| `getHistoricalPerformance(User $user, int $days)` | Get historical performance |
| `getCurrencyExposure(User $user)` | Get currency exposure |
| `getCurrencyRiskRating(string $currency)` | Get currency risk rating |

### WealthAdvisoryBoundaryGuard (`App\Services\WealthAdvisoryBoundaryGuard`)

| Function | Description |
|----------|-------------|
| `__construct(AuditService $auditService)` | Constructor |
| `validateNonDirectiveLanguage(string $insightText)` | Validate non-directive language |
| `logInsightGeneration(...)` | Log insight generation |
| `enforceNoExecutionPath(...)` | Block execution from insights |
| `getRequiredDisclaimers(string $insightType)` | Get required disclaimers |
| `wrapWithDisclaimers(...)` | Wrap insight with disclaimers |
| `validateInsightRequest(...)` | Validate insight request |
| `sanitizeInsightText(string $text)` | Sanitize directive language |

### AggregationEngine (`App\Services\AggregationEngine`)

| Function | Description |
|----------|-------------|
| `__construct(...)` | Constructor |
| `aggregateAccounts(User $user)` | Aggregate all user accounts |
| `refreshAccount(LinkedAccount $account)` | Refresh single account |
| `normalizeAccountData(...)` | Normalize account data |
| `calculateNetWorth(User $user)` | Calculate user net worth |

### RiskEngine (`App\Services\RiskEngine`)

| Function | Description |
|----------|-------------|
| `__construct()` | Constructor |
| `calculatePortfolioRisk(array $allocation)` | Calculate portfolio risk |
| `assessTransactionRisk(...)` | Assess transaction risk |
| `calculateUserRiskScore(User $user)` | Calculate user risk score |
| `getComplianceFlags(...)` | Get compliance flags |

### FeeEngine (`App\Services\FeeEngine`)

| Function | Description |
|----------|-------------|
| `__construct(...)` | Constructor |
| `calculateFee(...)` | Calculate transaction fee |
| `applyDiscount(...)` | Apply fee discount |
| `getFeeSchedule(...)` | Get fee schedule |
| `recordFee(...)` | Record fee collection |
| `refundFee(...)` | Refund fee |
| `calculateMerchantFee(...)` | Calculate merchant fee |
| `getTierDiscount(...)` | Get tier-based discount |
| `validateFeeStructure(...)` | Validate fee structure |

### ComplianceEngine (`App\Services\ComplianceEngine`)

| Function | Description |
|----------|-------------|
| `__construct(...)` | Constructor |
| `checkTransaction(...)` | Check transaction compliance |
| `screenUser(User $user)` | Screen user for compliance |
| `generateReport(...)` | Generate compliance report |
| `flagSuspiciousActivity(...)` | Flag suspicious activity |

### ConsentService (`App\Services\ConsentService`)

| Function | Description |
|----------|-------------|
| `__construct(...)` | Constructor |
| `grantConsent(...)` | Grant account consent |
| `revokeConsent(...)` | Revoke account consent |
| `validateConsent(...)` | Validate consent status |
| `refreshConsent(...)` | Refresh consent |

### CrossBorderOrchestrationEngine (`App\Services\CrossBorderOrchestrationEngine`)

| Function | Description |
|----------|-------------|
| `__construct(...)` | Constructor |
| `initiateTransfer(...)` | Initiate cross-border transfer |
| `routeToCorridors(...)` | Route to payment corridors |
| `executeSourceLeg(...)` | Execute source leg |
| `executeDestinationLeg(...)` | Execute destination leg |
| `handleFxConversion(...)` | Handle FX conversion |

### P2PMarketplaceEngine (`App\Services\P2PMarketplaceEngine`)

| Function | Description |
|----------|-------------|
| `__construct(...)` | Constructor |
| `createOffer(...)` | Create P2P offer |
| `findMatches(FxOffer $offer)` | Find matching offers |
| `matchOffers(...)` | Match two offers |
| `executeMatch(...)` | Execute matched trade |
| `cancelOffer(FxOffer $offer)` | Cancel offer |
| `getActiveOffers(...)` | Get active offers |
| `calculateMatchScore(...)` | Calculate match score |
| `validateOffer(...)` | Validate offer parameters |
| `getMarketDepth(...)` | Get market depth |
| `getRecentTrades(...)` | Get recent trades |
| `getUserOfferHistory(User $user)` | Get user offer history |

### SmartEscrowEngine (`App\Services\SmartEscrowEngine`)

| Function | Description |
|----------|-------------|
| `__construct(...)` | Constructor |
| `createEscrow(...)` | Create escrow |
| `fundEscrow(...)` | Fund escrow |
| `releaseEscrow(...)` | Release escrow funds |
| `disputeEscrow(...)` | Initiate escrow dispute |
| `refundEscrow(...)` | Refund escrow |

### EscrowStateMachine (`App\Services\EscrowStateMachine`)

| Function | Description |
|----------|-------------|
| `__construct(...)` | Constructor |
| `transitionTo(...)` | Transition escrow state |
| `canTransition(...)` | Check if transition allowed |
| `getAvailableTransitions(...)` | Get available transitions |
| `validateState(...)` | Validate current state |
| `handleTimeout(...)` | Handle escrow timeout |
| `recordStateChange(...)` | Record state change |
| `getStateHistory(...)` | Get state history |
| `rollback(...)` | Rollback state |
| `forceState(...)` | Force state (admin only) |
| `isTerminalState(...)` | Check if terminal state |

### MerchantOrchestrationEngine (`App\Services\MerchantOrchestrationEngine`)

| Function | Description |
|----------|-------------|
| `__construct(...)` | Constructor |
| `registerMerchant(...)` | Register new merchant |
| `verifyMerchant(Merchant $merchant)` | Verify merchant |
| `setSettlementAccount(...)` | Set settlement account |
| `registerDevice(...)` | Register POS device |
| `deactivateDevice(...)` | Deactivate device |
| `processPayment(...)` | Process merchant payment |
| `generateQrCode(...)` | Generate payment QR |
| `getSettlementReport(...)` | Get settlement report |
| `updateMerchantStatus(...)` | Update merchant status |

### PaymentRequestEngine (`App\Services\PaymentRequestEngine`)

| Function | Description |
|----------|-------------|
| `__construct(...)` | Constructor |
| `createRequest(...)` | Create payment request |
| `cancelRequest(PaymentRequest $request)` | Cancel request |
| `fulfillRequest(...)` | Fulfill payment request |
| `expireRequests()` | Expire old requests |
| `generateQrCode(PaymentRequest $request)` | Generate QR code |
| `validateRequest(...)` | Validate request |
| `sendNotification(...)` | Send notification |

### SubscriptionEngine (`App\Services\SubscriptionEngine`)

| Function | Description |
|----------|-------------|
| `__construct(...)` | Constructor |
| `subscribe(User $user, SubscriptionPlan $plan)` | Subscribe user to plan |
| `cancel(Subscription $subscription)` | Cancel subscription |
| `upgrade(Subscription $subscription, SubscriptionPlan $plan)` | Upgrade subscription |
| `downgrade(...)` | Downgrade subscription |
| `renew(Subscription $subscription)` | Renew subscription |
| `checkEntitlement(User $user, string $feature)` | Check feature entitlement |
| `getUsage(User $user)` | Get usage statistics |
| `processExpiredSubscriptions()` | Process expired subscriptions |
| `calculateProration(...)` | Calculate proration |

### NotificationService (`App\Services\NotificationService`)

| Function | Description |
|----------|-------------|
| `__construct(...)` | Constructor |
| `send(User $user, string $type, array $data)` | Send notification |
| `sendEmail(...)` | Send email notification |
| `sendPush(...)` | Send push notification |
| `sendSms(...)` | Send SMS notification |
| `markAsRead(Notification $notification)` | Mark as read |
| `getUnread(User $user)` | Get unread notifications |
| `getPreferences(User $user)` | Get notification preferences |
| `updatePreferences(...)` | Update preferences |
| `broadcast(...)` | Broadcast to multiple users |
| `scheduleNotification(...)` | Schedule notification |
| `cancelScheduled(...)` | Cancel scheduled notification |
| `getNotificationHistory(User $user)` | Get notification history |
| `deleteOldNotifications()` | Delete old notifications |
| `sendBatch(...)` | Send batch notifications |
| `validateChannel(...)` | Validate notification channel |

### SecurityService (`App\Services\SecurityService`)

| Function | Description |
|----------|-------------|
| `__construct(...)` | Constructor |
| `validateRequest(...)` | Validate request security |
| `logSecurityEvent(...)` | Log security event |
| `checkIpReputation(...)` | Check IP reputation |
| `validateDevice(...)` | Validate device |
| `enforceRateLimit(...)` | Enforce rate limiting |
| `detectAnomalies(...)` | Detect anomalous behavior |
| `generateSecurityToken(...)` | Generate security token |
| `validateSecurityToken(...)` | Validate security token |
| `reportSuspiciousActivity(...)` | Report suspicious activity |
| `getSecurityLogs(User $user)` | Get security logs |
| `blockUser(User $user, string $reason)` | Block user |

### DisputeEngine (`App\Services\DisputeEngine`)

| Function | Description |
|----------|-------------|
| `__construct(...)` | Constructor |
| `createDispute(...)` | Create dispute |
| `escalateDispute(Dispute $dispute)` | Escalate dispute |
| `resolveDispute(...)` | Resolve dispute |
| `addEvidence(...)` | Add evidence |
| `addComment(...)` | Add comment |
| `assignToAgent(...)` | Assign to agent |
| `getDisputeTimeline(Dispute $dispute)` | Get timeline |
| `calculateRefundAmount(...)` | Calculate refund |
| `closeDispute(...)` | Close dispute |
| `reopenDispute(Dispute $dispute)` | Reopen dispute |
| `getStatistics()` | Get dispute statistics |
| `autoResolve(...)` | Auto-resolve eligible disputes |
| `sendDisputeNotification(...)` | Send dispute notification |

### HealthCheckService (`App\Services\HealthCheckService`)

| Function | Description |
|----------|-------------|
| `__construct(...)` | Constructor |
| `checkAll()` | Run all health checks |
| `checkDatabase()` | Check database connectivity |
| `checkCache()` | Check cache service |
| `checkQueue()` | Check queue service |
| `checkExternalServices()` | Check external services |
| `checkDiskSpace()` | Check disk space |
| `getStatus()` | Get overall status |
| `recordMetrics(...)` | Record health metrics |
| `alertOnFailure(...)` | Send alerts on failure |

### KeyManagementService (`App\Services\KeyManagementService`)

| Function | Description |
|----------|-------------|
| `__construct(...)` | Constructor |
| `generateKey(...)` | Generate encryption key |
| `rotateKey(...)` | Rotate encryption key |
| `encrypt(...)` | Encrypt data |
| `decrypt(...)` | Decrypt data |
| `validateKey(...)` | Validate key |
| `revokeKey(...)` | Revoke key |
| `getKeyMetadata(...)` | Get key metadata |
| `scheduleRotation(...)` | Schedule key rotation |
| `auditKeyUsage(...)` | Audit key usage |
| `exportPublicKey(...)` | Export public key |
| `importKey(...)` | Import key |
| `backupKeys()` | Backup all keys |

### TokenVaultService (`App\Services\TokenVaultService`)

| Function | Description |
|----------|-------------|
| `__construct(...)` | Constructor |
| `storeToken(...)` | Store token securely |
| `retrieveToken(...)` | Retrieve token |
| `refreshToken(...)` | Refresh token |
| `revokeToken(...)` | Revoke token |
| `validateToken(...)` | Validate token |
| `encryptToken(...)` | Encrypt token |
| `decryptToken(...)` | Decrypt token |
| `rotateTokens(...)` | Rotate tokens |
| `getTokenMetadata(...)` | Get token metadata |
| `auditTokenAccess(...)` | Audit token access |
| `purgeExpiredTokens()` | Purge expired tokens |

### ZeroCustodyComplianceGuard (`App\Services\ZeroCustodyComplianceGuard`)

| Function | Description |
|----------|-------------|
| `__construct(AuditService $auditService)` | Constructor |
| `validateNoClientFundsHeld()` | Validate no client funds held |
| `preventBalanceMutation(...)` | Prevent balance mutation |
| `enforceInstructionOnlyModel(...)` | Enforce instruction-only model |
| `validatePassThroughFlow(...)` | Validate pass-through flow |
| `getComplianceAttestation()` | Get compliance attestation |
| `validateServiceNonCustodial(...)` | Validate service non-custodial |

### TransactionReconciliationEngine (`App\Services\TransactionReconciliationEngine`)

| Function | Description |
|----------|-------------|
| `__construct(AuditService $auditService)` | Constructor |
| `validateDebit(TransactionIntent $transaction)` | Validate debit leg |
| `validateCredit(TransactionIntent $transaction)` | Validate credit leg |
| `validateFXExecution(...)` | Validate FX execution |
| `matchAmountsAcrossLegs(...)` | Match amounts across legs |
| `finalizeTransaction(...)` | Finalize transaction |

### FXDiscoveryEngine (`App\Services\FXDiscoveryEngine`)

| Function | Description |
|----------|-------------|
| `__construct(...)` | Constructor |
| `discoverProviders(...)` | Discover FX providers |
| `getQuotes(...)` | Get FX quotes |
| `selectBestQuote(...)` | Select best quote |

### FXRFQBroadcastService (`App\Services\FXRFQBroadcastService`)

| Function | Description |
|----------|-------------|
| `__construct(...)` | Constructor |
| `broadcastRFQ(...)` | Broadcast RFQ to providers |
| `collectResponses(...)` | Collect provider responses |
| `selectProvider(...)` | Select winning provider |
| `executeWithProvider(...)` | Execute with selected provider |

### PrivacyComplianceService (`App\Services\PrivacyComplianceService`)

| Function | Description |
|----------|-------------|
| `__construct(...)` | Constructor |
| `handleDataExportRequest(User $user)` | Handle data export request |
| `handleDataDeletionRequest(User $user)` | Handle data deletion request |
| `anonymizeData(...)` | Anonymize user data |
| `getDataRetentionPolicy()` | Get data retention policy |
| `enforceRetentionPolicy()` | Enforce retention policy |
| `generatePrivacyReport(User $user)` | Generate privacy report |
| `logDataAccess(...)` | Log data access |
| `validateConsent(...)` | Validate privacy consent |
| `updateConsent(...)` | Update privacy consent |

---

## Models

### User (`App\Models\User`)

| Function | Description |
|----------|-------------|
| `linkedAccounts(): HasMany` | Get user's linked accounts |
| `transactionIntents(): HasMany` | Get user's transaction intents |
| `auditLogs(): HasMany` | Get user's audit logs |
| `aggregatedAccounts(): HasMany` | Get aggregated accounts |
| `institutionTokens(): HasMany` | Get institution tokens |
| `crossBorderTransactions(): HasMany` | Get cross-border transactions |
| `paymentRequests(): HasMany` | Get payment requests |
| `fxOffers(): HasMany` | Get FX offers |
| `merchant(): HasOne` | Get merchant profile |
| `subscription(): HasOne` | Get active subscription |
| `subscriptions(): HasMany` | Get all subscriptions |
| `wealthPortfolio(): HasOne` | Get wealth portfolio |
| `securityLogs(): HasMany` | Get security logs |
| `isAdmin(): bool` | Check if user is admin |
| `isRegulator(): bool` | Check if user is regulator |
| `isPrivileged(): bool` | Check if user has privileges |
| `canModifyData(): bool` | Check if can modify data |
| `isKycVerified(): bool` | Check KYC verification status |
| `isMerchant(): bool` | Check if user is merchant |
| `hasActiveSubscription(): bool` | Check subscription status |

### TransactionIntent (`App\Models\TransactionIntent`)

| Function | Description |
|----------|-------------|
| `user(): BelongsTo` | Get owning user |
| `issuerAccount(): BelongsTo` | Get issuer account |
| `paymentInstruction(): HasOne` | Get payment instruction |
| `isPending(): bool` | Check if pending |
| `isConfirmed(): bool` | Check if confirmed |
| `isExecuted(): bool` | Check if executed |
| `isFailed(): bool` | Check if failed |

### LinkedAccount (`App\Models\LinkedAccount`)

| Function | Description |
|----------|-------------|
| `user(): BelongsTo` | Get owning user |
| `institution(): BelongsTo` | Get linked institution |
| `transactions(): HasMany` | Get account transactions |
| `isActive(): bool` | Check if active |
| `isConsentValid(): bool` | Check consent validity |

### WealthPortfolio (`App\Models\WealthPortfolio`)

| Function | Description |
|----------|-------------|
| `user(): BelongsTo` | Get owning user |
| `getRiskLevel(): string` | Get risk level |
| `isStale(): bool` | Check if data is stale |
| `updateCalculation(array $data)` | Update calculation |
| `getCurrencyPercentage(string $currency): float` | Get currency percentage |
| `getDiversificationScore(): float` | Get diversification score |

### Subscription (`App\Models\Subscription`)

| Function | Description |
|----------|-------------|
| `user(): BelongsTo` | Get owning user |
| `plan(): BelongsTo` | Get subscription plan |
| `isActive(): bool` | Check if active |
| `isExpired(): bool` | Check if expired |
| `cancel(string $reason)` | Cancel subscription |
| `renew()` | Renew subscription |
| `getDaysRemaining(): int` | Get days remaining |
| `scopeActive($query)` | Scope to active subscriptions |

### Merchant (`App\Models\Merchant`)

| Function | Description |
|----------|-------------|
| `user(): BelongsTo` | Get owning user |
| `devices(): HasMany` | Get merchant devices |
| `settlementAccount(): BelongsTo` | Get settlement account |
| `transactions(): HasMany` | Get transactions |
| `isVerified(): bool` | Check verification status |
| `canProcessPayments(): bool` | Check payment capability |
| `getActiveDevices()` | Get active devices |
| `getSettlementBalance()` | Get settlement balance |

### FxOffer (`App\Models\FxOffer`)

| Function | Description |
|----------|-------------|
| `user(): BelongsTo` | Get owning user |
| `sourceAccount(): BelongsTo` | Get source account |
| `matches(): HasMany` | Get matched offers |
| `isActive(): bool` | Check if active |
| `isExpired(): bool` | Check if expired |
| `cancel()` | Cancel offer |
| `scopeActive($query)` | Scope to active offers |
| `scopeForCurrencyPair($query, ...)` | Scope by currency pair |
| `getEffectiveRate(): float` | Get effective rate |
| `getRemainingAmount(): float` | Get remaining amount |
| `isFullyMatched(): bool` | Check if fully matched |
| `getMatchPercentage(): float` | Get match percentage |

### Dispute (`App\Models\Dispute`)

| Function | Description |
|----------|-------------|
| `user(): BelongsTo` | Get disputing user |
| `transaction(): BelongsTo` | Get disputed transaction |
| `evidence(): HasMany` | Get evidence |
| `comments(): HasMany` | Get comments |
| `assignedAgent(): BelongsTo` | Get assigned agent |
| `isOpen(): bool` | Check if open |
| `isResolved(): bool` | Check if resolved |
| `escalate()` | Escalate dispute |
| `resolve(string $resolution)` | Resolve dispute |
| `canBeEscalated(): bool` | Check if can escalate |
| `getDaysOpen(): int` | Get days open |
| `scopeOpen($query)` | Scope to open disputes |
| `scopePending($query)` | Scope to pending |
| `scopeForUser($query, User $user)` | Scope by user |
| `getTimeline()` | Get dispute timeline |

### PaymentRequest (`App\Models\PaymentRequest`)

| Function | Description |
|----------|-------------|
| `user(): BelongsTo` | Get requesting user |
| `linkedAccount(): BelongsTo` | Get linked account |
| `isPending(): bool` | Check if pending |
| `isFulfilled(): bool` | Check if fulfilled |
| `isCancelled(): bool` | Check if cancelled |
| `isExpired(): bool` | Check if expired |
| `cancel()` | Cancel request |
| `fulfill(...)` | Fulfill request |
| `scopePending($query)` | Scope to pending |
| `scopeExpired($query)` | Scope to expired |

### AuditLog (`App\Models\AuditLog`)

| Function | Description |
|----------|-------------|
| `user(): BelongsTo` | Get associated user |
| `scopeForUser($query, User $user)` | Scope by user |
| `scopeForAction($query, string $action)` | Scope by action |
| `scopeRecent($query, int $days)` | Scope to recent entries |

---

## Actions

### CreateNewUser (`App\Actions\Fortify\CreateNewUser`)

| Function | Description |
|----------|-------------|
| `create(array $input): User` | Create new user with validation |

### ResetUserPassword (`App\Actions\Fortify\ResetUserPassword`)

| Function | Description |
|----------|-------------|
| `reset(User $user, array $input)` | Reset user password |

---

## Connectors

### BankConnector (`App\Connectors\BankConnector`)

| Function | Description |
|----------|-------------|
| `__construct(...)` | Constructor |
| `connect(Institution $institution)` | Connect to bank |
| `getAccounts()` | Get accounts |
| `getBalance(string $accountId)` | Get account balance |
| `getTransactions(...)` | Get transactions |
| `initiatePayment(...)` | Initiate payment |
| `confirmPayment(...)` | Confirm payment |
| `getPaymentStatus(...)` | Get payment status |
| `refreshToken()` | Refresh access token |
| `revokeAccess()` | Revoke access |
| `validateCredentials()` | Validate credentials |
| `getInstitutionCapabilities()` | Get capabilities |

### WalletConnector (`App\Connectors\WalletConnector`)

| Function | Description |
|----------|-------------|
| `__construct(...)` | Constructor |
| `connect(Institution $institution)` | Connect to wallet |
| `getBalance()` | Get wallet balance |
| `sendPayment(...)` | Send payment |
| `receivePayment(...)` | Receive payment |
| `getTransactionHistory()` | Get transaction history |
| `validateAddress(...)` | Validate wallet address |
| `estimateFee(...)` | Estimate transaction fee |
| `getExchangeRate(...)` | Get exchange rate |
| `refreshConnection()` | Refresh connection |
| `disconnect()` | Disconnect wallet |
| `getWalletInfo()` | Get wallet information |

---

## Middleware

### EnsureUserIsAdmin (`App\Http\Middleware\EnsureUserIsAdmin`)

| Function | Description |
|----------|-------------|
| `handle(Request $request, Closure $next)` | Check admin status |

### HandleAppearance (`App\Http\Middleware\HandleAppearance`)

| Function | Description |
|----------|-------------|
| `handle(Request $request, Closure $next)` | Handle appearance settings |

### HandleInertiaRequests (`App\Http\Middleware\HandleInertiaRequests`)

| Function | Description |
|----------|-------------|
| `version(Request $request)` | Get asset version |
| `share(Request $request)` | Share data with Inertia |

---

## Jobs

### ProcessTransaction

| Function | Description |
|----------|-------------|
| `__construct(TransactionIntent $transaction)` | Constructor |
| `handle()` | Process the transaction |
| `failed(Throwable $exception)` | Handle job failure |

### RefreshAccountData

| Function | Description |
|----------|-------------|
| `__construct(LinkedAccount $account)` | Constructor |
| `handle()` | Refresh account data |

### SendNotification

| Function | Description |
|----------|-------------|
| `__construct(...)` | Constructor |
| `handle()` | Send notification |

### ProcessSettlement

| Function | Description |
|----------|-------------|
| `__construct(...)` | Constructor |
| `handle()` | Process settlement batch |

### ExpirePaymentRequests

| Function | Description |
|----------|-------------|
| `handle()` | Expire old payment requests |

---

## Events

### TransactionCreated

| Function | Description |
|----------|-------------|
| `__construct(TransactionIntent $transaction)` | Constructor |
| `broadcastOn()` | Get broadcast channels |

### TransactionExecuted

| Function | Description |
|----------|-------------|
| `__construct(TransactionIntent $transaction)` | Constructor |
| `broadcastOn()` | Get broadcast channels |

### FxOfferMatched

| Function | Description |
|----------|-------------|
| `__construct(FxOffer $offer, FxOffer $counterOffer)` | Constructor |
| `broadcastOn()` | Get broadcast channels |

### PaymentRequestFulfilled

| Function | Description |
|----------|-------------|
| `__construct(PaymentRequest $request)` | Constructor |
| `broadcastOn()` | Get broadcast channels |

### MerchantVerified

| Function | Description |
|----------|-------------|
| `__construct(Merchant $merchant)` | Constructor |
| `broadcastOn()` | Get broadcast channels |

### SuspiciousActivityDetected

| Function | Description |
|----------|-------------|
| `__construct(User $user, array $details)` | Constructor |
| `broadcastOn()` | Get broadcast channels |

---

## Frontend Composables (TypeScript)

### useAppearance (`resources/js/composables/useAppearance.ts`)

| Function | Description |
|----------|-------------|
| `updateTheme(value: Appearance): void` | Update theme (light/dark/system) |
| `initializeTheme(): void` | Initialize theme on page load |
| `useAppearance(): UseAppearanceReturn` | Composable for appearance management |

### useInitials (`resources/js/composables/useInitials.ts`)

| Function | Description |
|----------|-------------|
| `getInitials(fullName?: string): string` | Get initials from name |
| `useInitials(): UseInitialsReturn` | Composable for initials |

---

*Last updated: February 2026*
