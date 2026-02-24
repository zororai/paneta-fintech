# Account Linking Flow - Technical Implementation

## Overview

The account linking process has been completely refactored to align with the technical architecture described on page 55 of the technical design document. The system now implements a proper **Account Aggregation Initiation** flow with all required steps.

---

## Complete Flow Sequence

### Step 1: Country Selection
**Purpose:** Identify the jurisdiction and regulatory framework

**User Action:**
- User clicks "Link Account" button
- System presents country selection screen
- User selects country from available options:
  - 🇿🇼 Zimbabwe (ZW)
  - 🇿🇦 South Africa (ZA)
  - 🇺🇸 United States (US)
  - 🇬🇧 United Kingdom (GB)
  - 🇰🇪 Kenya (KE)
  - 🇳🇬 Nigeria (NG)

**System Action:**
- Stores selected country code
- Filters available institutions by country
- Advances to Step 2

---

### Step 2: Provider Category Selection
**Purpose:** Filter institutions by service type

**User Action:**
- User selects provider category:
  - **Banks** - Commercial Banks & Retail Banking
  - **Digital Wallets** - Mobile Money & E-Wallets
  - **Card Networks** - Credit & Debit Card Issuers

**System Action:**
- Filters institutions by selected category type
- Combines country + category filters
- Advances to Step 3

---

### Step 3: Institution Selection
**Purpose:** Select specific financial institution

**User Action:**
- Views filtered list of institutions from selected country/category
- Clicks on specific institution (e.g., CBZ Bank, EcoCash, etc.)

**System Action:**
- Stores selected institution ID
- Displays institution details
- Advances to Step 4

---

### Step 4: Account Details Entry
**Purpose:** Capture account-specific information

**User Action:**
- Enters **Account Holder Name** (full legal name as appears on account)
- Enters **Account Number** (institution-specific identifier)
- Selects **Account Currency** (USD, ZWL, EUR, GBP, ZAR)

**Validation:**
- Account holder name: minimum 2 characters, maximum 255
- Account number: minimum 5 characters, maximum 50
- Currency: must be 3-letter ISO code

**System Action:**
- Validates all inputs
- Checks for duplicate account linkage
- Advances to Step 5 when valid

---

### Step 5: Consent & Authorization
**Purpose:** Obtain explicit user consent for data access

**Display Elements:**

1. **Consent Summary**
   - Institution name
   - Country
   - Account holder name
   - Account number
   - Currency

2. **Authorization Permissions**
   User authorizes PANÉTA to:
   - ✓ Access account balance and transaction history
   - ✓ Initiate payment instructions on behalf of user
   - ✓ Receive real-time account updates

3. **Consent Terms**
   - Consent validity: 90 days
   - Can be revoked at any time
   - Governed by selected country's regulations

4. **Explicit Consent Checkbox**
   - User must check: "I grant consent and authorize PANÉTA to access my account"

**User Action:**
- Reviews consent summary
- Reads authorization permissions
- Checks consent checkbox
- Clicks "Complete Linking"

**System Action:**
- Generates consent token (64-character secure token)
- Creates linked account record with:
  - User ID
  - Institution ID
  - Country code
  - Account identifier
  - Account holder name
  - Currency
  - Mock balance (demo: random 100-50,000)
  - Consent token (encrypted)
  - Consent expiry (90 days from now)
  - Status: 'active'
- Logs audit event: `account_linked`
- Displays success message
- Closes dialog
- Refreshes account list

---

## Visual Progress Indicator

The dialog displays a 5-step progress bar:
```
[1] ─── [2] ─── [3] ─── [4] ─── [5]
 ✓       ✓       ✓       •       ○
```

- ✓ = Completed step
- • = Current step
- ○ = Pending step

**Navigation:**
- "Back" button: Available on steps 2-5
- "Next" button: Available on steps 1-4 (disabled until step requirements met)
- "Complete Linking" button: Only on step 5 (disabled until consent granted)
- "Cancel" button: Available on all steps

---

## Dashboard Presentation

### Linked Account Card Display

Each linked account shows:

**Header:**
- Institution icon (based on type)
- Institution name
- Account identifier
- Status badge (active/revoked/expired)

**Balance:**
- Current balance in account currency
- Formatted with currency symbol

**Action Row (3 columns):**
1. **Type** - Badge showing account type (bank/wallet/card)
2. **View Details** - Button to view full account details
3. **Statements** - Button to access transaction statements

**Management Buttons (for active accounts):**
- **Refresh** - Renew consent token
- **Revoke** - Revoke access and mark as revoked

---

## Database Schema

### Migration: `add_account_holder_name_and_country_to_linked_accounts_table`

```sql
ALTER TABLE linked_accounts 
ADD COLUMN account_holder_name VARCHAR(255) NULL AFTER account_identifier,
ADD COLUMN country VARCHAR(2) NULL AFTER institution_id;
```

### Updated `linked_accounts` Table Structure

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| user_id | bigint | Foreign key to users |
| institution_id | bigint | Foreign key to institutions |
| **country** | varchar(2) | **NEW: ISO country code** |
| account_identifier | varchar(255) | Account number |
| **account_holder_name** | varchar(255) | **NEW: Legal account holder name** |
| currency | varchar(3) | ISO currency code |
| mock_balance | decimal(18,2) | Demo balance |
| consent_token | text (encrypted) | OAuth-style token |
| consent_expires_at | timestamp | Token expiry |
| status | enum | active/revoked/expired |
| created_at | timestamp | Record creation |
| updated_at | timestamp | Last update |

---

## Backend Implementation

### Controller: `LinkedAccountController::store()`

**Validation Rules:**
```php
[
    'institution_id' => ['required', 'exists:institutions,id'],
    'country' => ['required', 'string', 'size:2'],
    'account_number' => ['required', 'string', 'min:5', 'max:50'],
    'account_holder_name' => ['required', 'string', 'min:2', 'max:255'],
    'currency' => ['required', 'string', 'size:3'],
]
```

**Process:**
1. Validate input
2. Check for duplicate linkage
3. Call `ConsentService::completeConsent()`
4. Log audit event
5. Return success response

### Service: `ConsentService::completeConsent()`

**Parameters:**
- `User $user` - Authenticated user
- `Institution $institution` - Selected institution
- `string $currency` - Account currency
- `string $accountNumber` - Account identifier
- `string $accountHolderName` - Legal name
- `string $country` - Country code

**Actions:**
1. Generate secure consent token (64 chars)
2. Generate mock balance (100-50,000)
3. Create LinkedAccount record
4. Set consent expiry (90 days)
5. Return created account

---

## Audit & Regulatory Visibility

### Audit Log Entry

When account is linked, system logs:

```json
{
    "action": "account_linked",
    "entity_type": "linked_account",
    "entity_id": 123,
    "user_id": 456,
    "metadata": {
        "institution_id": 789,
        "institution_name": "CBZ Bank",
        "country": "ZW",
        "account_holder_name": "John Doe",
        "account_number": "ACC-XXXXXXXX",
        "currency": "USD"
    },
    "ip_address": "192.168.1.1",
    "user_agent": "Mozilla/5.0...",
    "timestamp": "2026-02-23T20:30:00Z"
}
```

### Regulatory Compliance

- **Consent tracking**: Full audit trail of consent grant/revoke
- **Data minimization**: Only required fields collected
- **User control**: Can revoke consent at any time
- **Transparency**: Clear display of what data is accessed
- **Expiry**: Automatic consent expiration after 90 days

---

## Frontend Components

### File: `resources/js/pages/Paneta/LinkedAccounts.vue`

**Key Features:**
- Multi-step wizard with progress indicator
- Reactive step validation
- Country/category/institution filtering
- Form validation with error display
- Consent summary and authorization
- Responsive grid layout for accounts
- Type-safe TypeScript implementation

**State Management:**
```typescript
const linkingStep = ref(1);              // Current step (1-5)
const selectedCountry = ref<string | null>(null);
const selectedCategory = ref<string | null>(null);
const selectedInstitution = ref<number | null>(null);
const consentGranted = ref(false);
```

**Form Data:**
```typescript
const form = useForm({
    institution_id: null,
    country: '',
    account_number: '',
    account_holder_name: '',
    currency: 'USD',
});
```

---

## Testing the Flow

### Local Development

1. Navigate to: `http://paneta.test/paneta/accounts`
2. Click "Link Account" button
3. Follow all 5 steps:
   - Select country
   - Select category
   - Select institution
   - Enter account details
   - Grant consent
4. Verify account appears in dashboard
5. Test "View Details" and "Statements" buttons
6. Test "Refresh" and "Revoke" actions

### Production Deployment

After deploying to production:

1. Run migration:
   ```bash
   php artisan migrate --force
   ```

2. Clear caches:
   ```bash
   php artisan config:clear
   php artisan view:clear
   php artisan cache:clear
   ```

3. Test complete flow on production URL

---

## Key Differences from Previous Implementation

### Before (Incorrect)
- ❌ Single-step dialog
- ❌ No country selection
- ❌ Category tabs only (not sequential)
- ❌ No account holder name
- ❌ No explicit consent flow
- ❌ Showed "Consent expires" on cards
- ❌ Direct linking without proper authorization

### After (Correct - Aligned with Technical Design)
- ✅ 5-step sequential wizard
- ✅ Country selection first
- ✅ Provider category filtering
- ✅ Institution selection from filtered list
- ✅ Account holder name required
- ✅ Full consent & authorization flow
- ✅ Shows Type/Details/Statements on cards
- ✅ Proper consent granting with checkbox
- ✅ Complete audit trail

---

## Summary

The account linking flow now fully implements the **Account Aggregation Initiation** process as specified in the technical design (page 55), including:

1. ✅ Country-based filtering
2. ✅ Provider category selection (bank/wallet/card)
3. ✅ Institution selection
4. ✅ Account details capture (number + holder name)
5. ✅ Consent & authorization flow
6. ✅ Consent granting with explicit checkbox
7. ✅ Initial data fetch (mock balance)
8. ✅ User dashboard presentation
9. ✅ Audit & regulatory visibility

The demo now properly demonstrates the real flow from initiation through to completion, with all intermediate steps visible and required.
