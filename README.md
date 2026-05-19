# GuestHouse Manager v3.0 — WordPress Plugin

Complete Property Management System for guest houses, boutique hotels, B&Bs, and co-working spaces.

---

## Quick Start

1. Upload `guesthouse-manager.zip` → **Plugins → Add New → Upload Plugin**
2. Activate the plugin
3. Go to **GuestHouse → Settings** and configure your hotel name, currency, and payment gateways
4. Add the booking form to any page: `[ghm_booking_form]`
5. Add the guest portal to any page: `[ghm_guest_portal]`

---

## All Features

### Core Management
| Feature | Details |
|---------|---------|
| **Rooms** | Add/edit rooms with type, capacity, pricing, amenities, floor, room number |
| **Workspaces** | Conference rooms and co-working spaces with hourly billing |
| **Bookings** | Full reservation workflow: Booked → Confirmed → Checked-In → Checked-Out |
| **Customers / CRM** | Guest profiles with ID verification, stay history, lifetime spend |
| **Payments** | Cash, card, bank transfer, mobile money — with partial payment tracking |
| **Staff** | Create staff accounts with GHM roles, shifts, departments |

### Billing Logic
| Room Type | Billing |
|-----------|---------|
| Room / Suite / Apartment | Per night |
| Event Hall / Meeting Room | Per day |
| Workspace / Co-working | Per hour |

### Status Flow
```
New Booking → BOOKED (unpaid) → CONFIRMED (paid) → CHECKED IN → CHECKED OUT
```

---

## Payment Gateways

### Paystack
- Card, Bank Transfer, USSD, Mobile Money
- Test & Live mode
- Webhook verification
- Configure: **Settings → Payments → Paystack**

### Flutterwave
- Card, Bank Transfer, USSD, Mobile Money
- 30+ African currencies (Nigeria, Ghana, Kenya, South Africa, Uganda...)
- Webhook verification
- Configure: **Settings → Payments → Flutterwave**

Both gateways auto-confirm bookings upon successful payment.

---

## Guest Portal

Add `[ghm_guest_portal]` to any page. Guests log in with **booking reference + email** (no WordPress account needed).

**Portal features:**
- View full booking details and dates
- Download invoice / receipt
- See payment history
- Pay outstanding balance online (Paystack)
- **Request services during their stay** (housekeeping, room service, transport, etc.)
- **Submit a star review** after checkout (with cleanliness, service, comfort, value ratings)

---

## Housekeeping Board

**GuestHouse → Housekeeping**

- Live room status board: 🔴 Dirty → 🟡 Being Cleaned → 🟢 Clean → ✅ Inspected
- Assign rooms to specific staff members
- Set priority (Normal / High / Urgent)
- Auto-marks rooms dirty when a guest checks out
- Auto-refreshes every 60 seconds

---

## Maintenance Requests

**GuestHouse → Maintenance**

- Log issues by room (plumbing, electrical, HVAC, furniture, etc.)
- Priority levels: Low / Normal / High / Urgent
- Status workflow: Open → In Progress → Resolved
- Auto-sets room status to "Maintenance" when an issue is open
- Frees room when resolved

---

## Dynamic Pricing

**GuestHouse → Dynamic Pricing**

- Create rules for weekends, holidays, peak seasons
- Percentage (e.g. +20% on weekends) or fixed amount adjustments
- Apply to all rooms, specific room types, or individual rooms
- Date range and day-of-week targeting
- Priority system — higher priority rules applied first

---

## Discount Codes

**GuestHouse → Discounts**

- Percentage or fixed-amount codes
- Expiry dates, minimum booking amounts, max uses
- Applied on the public booking form with live price recalculation
- Shortcode form shows discount section after room and dates are selected

---

## Security Deposits

**GuestHouse → Deposits**

- Collect a refundable deposit separate from room charge
- Track by booking reference
- Refund with one click at checkout
- Forfeit (with reason) — automatically recorded as revenue
- Summary: Total held / refunded / forfeited

---

## WhatsApp Notifications

**Settings → WhatsApp**

Supports **Twilio**, **WhatsApp Cloud API (Meta)**, and **UltraMsg**.

Automatic messages for:
- New booking received
- Booking confirmed (after payment)
- Booking cancelled
- Payment receipt
- Pre-arrival reminder (sent automatically 24h before check-in)
- Post-checkout thank-you

Also notifies admin on new bookings.

---

## Automated Emails

All emails are sent automatically by the built-in scheduler:

| Trigger | When |
|---------|------|
| New booking | Immediately |
| Payment confirmed | Immediately |
| Pre-arrival reminder | 24–26 hours before check-in |
| Post-stay review request | 24–26 hours after checkout |
| Daily digest to admin | Every day at 8am |

---

## Revenue Forecasting

**GuestHouse → Forecast**

- Projects expected revenue for next 7 / 14 / 30 / 60 / 90 days
- Based on confirmed and booked reservations
- Daily breakdown with bar chart
- Shows "at risk" revenue (booked but unpaid)
- Weekends highlighted in gold

---

## Occupancy Calendar

**GuestHouse → Calendar**

- Monthly room availability calendar
- Colour-coded by booking status
- Navigate month by month
- Occupancy rate percentage with progress bar

---

## Reports & Analytics

**GuestHouse → Reports**

- Revenue trend (12 months) with bar + line chart
- Booking status breakdown (donut chart)
- Payment method breakdown
- Occupancy rate
- Top performing rooms
- Booking channel breakdown (website, walk-in, Booking.com, etc.)
- Recent activity feed

---

## Staff Activity Log

**GuestHouse → Activity Log**

- Every action logged: booking created/modified, payments, housekeeping updates, etc.
- Filter by date range
- Per-staff summary: total actions, booking actions, payment actions
- Export to CSV
- IP address tracking

---

## Role Permissions

**GuestHouse → Permissions**

Adjust which capabilities each role has via a visual checkbox matrix:

| Capability | GHM Staff | GHM Manager |
|-----------|-----------|-------------|
| Manage Rooms | ✗ | ✓ |
| Manage Bookings | ✓ | ✓ |
| Manage Customers | ✓ | ✓ |
| Manage Payments | ✗ | ✓ |
| View Reports | ✗ | ✓ |
| Manage Staff | ✗ | ✓ |

---

## PIN / Quick Login

**GuestHouse → Permissions → My PIN**

Set a 4–8 digit PIN for fast front-desk login.

Add `[ghm_pin_login]` to any page to show the PIN pad. Staff tap their PIN and are instantly logged in to the GHM dashboard. No typing usernames or passwords at a shared terminal.

---

## REST API

Base URL: `/wp-json/ghm/v1/`

Authentication: `X-GHM-API-Key: your_key` header (set in Settings → API)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/rooms` | GET | List rooms |
| `/rooms/available` | GET | Available rooms (?check_in=&check_out=) |
| `/bookings` | GET/POST | List or create bookings |
| `/bookings/{id}` | GET/PATCH | Get or update booking |
| `/customers` | GET/POST | List or create customers |
| `/payments` | GET/POST | List or record payments |
| `/reports/summary` | GET | Dashboard stats |
| `/reports/revenue` | GET | Revenue chart data |
| `/housekeeping` | GET/PATCH | Housekeeping board |
| `/ical/{room_id}` | GET | iCal feed for room |

---

## iCal / External Calendar Sync

Each room has an iCal feed URL visible in **Settings → Calendar**.

Import into:
- **Booking.com** → Rates & Availability → Calendar → Import
- **Airbnb** → Calendar → Availability → Import calendar
- **Google Calendar** → Other calendars → From URL
- Any calendar app that supports iCal

---

## Google Calendar Sync

**Settings → Calendar → Google Calendar Sync**

1. Create OAuth 2.0 credentials at [Google Cloud Console](https://console.cloud.google.com)
2. Enter Client ID and Secret in Settings
3. Click "Connect Google Calendar"
4. Bookings will appear automatically in your Google Calendar, colour-coded by status

---

## Tax Configuration

**Settings → Tax**

- Set VAT / GST / Service Charge rate (e.g. 7.5% for Nigerian VAT)
- Tax-inclusive or tax-exclusive pricing
- Tax shown separately on invoices
- Applied automatically to all bookings

---

## CSV / Accounting Export

**Payments page → Export Payments CSV** or **Export Bookings CSV**

Payment export columns: Date, Booking Ref, Guest, Email, Room, Amount, Currency, Method, Transaction ID, Notes

Booking export columns: Ref, Guest, Email, Phone, Room, Check-In, Check-Out, Adults, Children, Total, Paid, Balance, Status, Payment Status, Source, Created

---

## Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[ghm_booking_form]` | Multi-step booking form with Paystack/Flutterwave |
| `[ghm_booking_form type="workspace"]` | Workspace booking form |
| `[ghm_rooms_list]` | Available rooms grid |
| `[ghm_rooms_list type="workspace"]` | Available workspaces grid |
| `[ghm_booking_confirmation]` | Booking lookup / confirmation |
| `[ghm_guest_portal]` | Guest self-service portal |
| `[ghm_waitlist_form]` | Waiting list signup |
| `[ghm_waitlist_form room_id="5"]` | Waiting list for specific room |
| `[ghm_pin_login]` | Staff PIN login pad |

---

## Database Tables (12 tables)

| Table | Purpose |
|-------|---------|
| `ghm_rooms` | Rooms and workspaces |
| `ghm_bookings` | All reservations |
| `ghm_customers` | Guest CRM data |
| `ghm_payments` | Payment records |
| `ghm_staff` | Staff profiles |
| `ghm_activity_log` | Full audit trail |
| `ghm_housekeeping` | Room cleaning status |
| `ghm_maintenance` | Maintenance requests |
| `ghm_discounts` | Discount codes |
| `ghm_waitlist` | Waiting list entries |
| `ghm_deposits` | Security deposits |
| `ghm_pricing_rules` | Dynamic pricing rules |
| `ghm_service_requests` | Guest portal service requests |
| `ghm_reviews` | Guest reviews |

---

## File Structure (65 files)

```
guesthouse-manager/
├── guesthouse-manager.php           # Main plugin file
├── includes/
│   ├── class-ghm-install.php        # DB + roles setup
│   ├── class-ghm-rooms.php
│   ├── class-ghm-workspaces.php
│   ├── class-ghm-bookings.php
│   ├── class-ghm-customers.php
│   ├── class-ghm-staff.php
│   ├── class-ghm-payments.php
│   ├── class-ghm-reports.php
│   ├── class-ghm-ajax.php
│   ├── class-ghm-shortcodes.php
│   ├── class-ghm-emails.php
│   ├── class-ghm-paystack.php
│   ├── class-ghm-staff-access.php
│   ├── class-ghm-login-page.php
│   └── modules/
│       ├── class-ghm-housekeeping.php
│       ├── class-ghm-maintenance.php
│       ├── class-ghm-discounts.php
│       ├── class-ghm-waitlist.php
│       ├── class-ghm-channels.php
│       ├── class-ghm-invoice.php
│       ├── class-ghm-whatsapp.php
│       ├── class-ghm-rest-api.php
│       ├── class-ghm-integrations.php    # Google Calendar + Tax
│       ├── class-ghm-guest-portal.php
│       ├── class-ghm-dynamic-pricing.php
│       ├── class-ghm-deposits.php
│       ├── class-ghm-flutterwave.php
│       ├── class-ghm-scheduler.php       # Automated emails + cron
│       └── class-ghm-utilities.php       # Forecasting, CSV, Cache, PIN, Permissions
├── admin/
│   ├── class-ghm-admin.php
│   ├── css/ghm-admin.css
│   ├── js/ghm-admin.js
│   └── views/
│       ├── dashboard.php
│       ├── rooms.php / workspaces.php
│       ├── bookings.php / customers.php
│       ├── payments.php / staff.php
│       ├── reports.php / settings.php
│       └── modules/
│           ├── calendar.php
│           ├── housekeeping.php
│           ├── maintenance.php
│           ├── discounts.php
│           ├── waitlist.php
│           ├── deposits.php
│           ├── pricing.php
│           ├── forecast.php
│           ├── activity.php
│           ├── permissions.php
│           └── reviews.php
├── public/
│   ├── css/ghm-public.css + ghm-portal.css
│   └── js/ghm-public.js + ghm-portal.js
└── templates/
    ├── booking-form.php
    ├── rooms-list.php
    ├── booking-confirmation.php
    ├── waitlist-form.php
    ├── portal-login.php
    └── portal-dashboard.php
```

---

## Requirements

- WordPress 5.8+
- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.3+
