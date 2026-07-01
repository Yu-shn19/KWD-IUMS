/**
 * eKWD-IUMS onboarding tours (Driver.js)
 */
(function () {
    'use strict';

    var STORAGE_PREFIX = 'ekwd_iums_tours_';
    var userId = (window.EKWD_TOUR_CONFIG && window.EKWD_TOUR_CONFIG.userId) || 'guest';
    var storageKey = STORAGE_PREFIX + userId;

    function getCompletedTours() {
        try {
            var raw = localStorage.getItem(storageKey);
            return raw ? JSON.parse(raw) : [];
        } catch (e) {
            return [];
        }
    }

    function markTourComplete(tourId) {
        if (!tourId) return;
        var completed = getCompletedTours();
        if (completed.indexOf(tourId) === -1) {
            completed.push(tourId);
            localStorage.setItem(storageKey, JSON.stringify(completed));
        }
    }

    function isTourComplete(tourId) {
        return getCompletedTours().indexOf(tourId) !== -1;
    }

    function getSidebarScroller() {
        return document.querySelector('#accordionSidebar') || document.querySelector('.modern-sidebar');
    }

    function resetSidebarScroll() {
        var sidebar = getSidebarScroller();
        if (sidebar) {
            sidebar.scrollTop = 0;
        }
    }

    function scrollNavGroupIntoView(groupSelector) {
        var sidebar = getSidebarScroller();
        var group = document.querySelector(groupSelector);
        if (!sidebar || !group) {
            return;
        }
        var groupRect = group.getBoundingClientRect();
        var sideRect = sidebar.getBoundingClientRect();
        sidebar.scrollTop += (groupRect.top - sideRect.top) - 16;
    }

    function clearTourNavHighlights() {
        document.querySelectorAll('#accordionSidebar .ekwd-tour-nav-active').forEach(function (el) {
            el.classList.remove('ekwd-tour-nav-active');
        });
    }

    function setTourNavActive(groupSelector) {
        clearTourNavHighlights();
        var group = document.querySelector(groupSelector);
        if (group) {
            group.classList.add('ekwd-tour-nav-active');
        }
    }

    function expandCollapse(selector) {
        var panel = document.querySelector(selector);
        if (!panel) return;
        if (!panel.classList.contains('show') && window.jQuery) {
            window.jQuery(panel).collapse('show');
        }
    }

    function collapseAllSidebarMenus() {
        ['#collapseBootstrap', '#collapseForm', '#collapseTable', '#collapseReport', '#collapseOptions'].forEach(function (selector) {
            var panel = document.querySelector(selector);
            if (panel && panel.classList.contains('show') && window.jQuery) {
                window.jQuery(panel).collapse('hide');
            }
        });
    }

    function scrollSidebarToElement(element) {
        if (!element) return;
        var group = element.closest('.nav-item');
        if (group && group.id) {
            scrollNavGroupIntoView('#' + group.id);
            return;
        }
        var sidebar = getSidebarScroller();
        if (!sidebar) {
            element.scrollIntoView({ block: 'center', behavior: 'auto' });
            return;
        }
        var pad = 24;
        var elRect = element.getBoundingClientRect();
        var sideRect = sidebar.getBoundingClientRect();
        if (elRect.top < sideRect.top + pad) {
            sidebar.scrollTop -= (sideRect.top + pad - elRect.top);
        } else if (elRect.bottom > sideRect.bottom - pad) {
            sidebar.scrollTop += (elRect.bottom - sideRect.bottom + pad);
        }
    }

    function refreshDriverPosition(driver, delays) {
        if (!driver || typeof driver.refresh !== 'function') return;
        (delays || [80, 300]).forEach(function (delay) {
            setTimeout(function () { driver.refresh(); }, delay);
        });
    }

    function navigationSidebarStep(groupSelector, elementSelector, title, description) {
        return {
            element: elementSelector,
            onHighlightStarted: function () {
                collapseAllSidebarMenus();
                scrollNavGroupIntoView(groupSelector);
                setTourNavActive(groupSelector);
            },
            onDeselected: function () {
                clearTourNavHighlights();
            },
            onHighlighted: function (element, step, options) {
                scrollNavGroupIntoView(groupSelector);
                setTourNavActive(groupSelector);
                refreshDriverPosition(options.driver, [80, 200, 450, 700]);
            },
            popover: {
                title: title,
                description: description,
                side: 'right',
                align: 'start',
            },
        };
    }

    function sidebarNavStep(selector, title, description) {
        return {
            element: selector,
            onHighlightStarted: function (element) {
                collapseAllSidebarMenus();
                scrollSidebarToElement(element);
            },
            onHighlighted: function (element, step, options) {
                refreshDriverPosition(options.driver);
            },
            popover: {
                title: title,
                description: description,
                side: 'right',
                align: 'start',
            },
        };
    }

    function sidebarSubmenuStep(groupSelector, parentSelector, collapseSelector, title, description) {
        return {
            element: parentSelector,
            onHighlightStarted: function () {
                collapseAllSidebarMenus();
                expandCollapse(collapseSelector);
                scrollNavGroupIntoView(groupSelector);
                setTourNavActive(groupSelector);
            },
            onDeselected: function () {
                clearTourNavHighlights();
            },
            onHighlighted: function (element, step, options) {
                scrollNavGroupIntoView(groupSelector);
                setTourNavActive(groupSelector);
                refreshDriverPosition(options.driver, [120, 400, 700]);
            },
            popover: {
                title: title,
                description: description,
                side: 'right',
                align: 'start',
            },
        };
    }

    function filterSteps(steps) {
        return steps.filter(function (step) {
            if (!step.element) return true;
            return !!document.querySelector(step.element);
        });
    }

    function createDriver(steps, tourId, force) {
        if (!window.driver || !window.driver.js || !window.driver.js.driver) {
            return null;
        }

        var resolved = filterSteps(steps);
        if (!resolved.length) return null;

        var lastStep = resolved[resolved.length - 1];

        function isLastTourStep(step) {
            if (!step || !lastStep) return false;
            if (step === lastStep) return true;
            if (step.element && lastStep.element && step.element === lastStep.element) return true;
            return false;
        }

        return window.driver.js.driver({
            showProgress: true,
            progressText: '{{current}} of {{total}}',
            nextBtnText: 'Next',
            prevBtnText: 'Back',
            doneBtnText: 'Done',
            popoverClass: 'ekwd-tour-popover',
            overlayColor: 'rgba(15, 23, 42, 0.65)',
            stagePadding: 8,
            stageRadius: 10,
            allowClose: true,
            steps: resolved,
            onDestroyed: function (element, step, options) {
                clearTourNavHighlights();
                if (tourId === 'navigation') {
                    collapseAllSidebarMenus();
                }

                // Driver.js clears activeIndex before onDestroyed; use the active step instead.
                var reachedEnd = isLastTourStep(step);
                if (!reachedEnd || !tourId) {
                    return;
                }

                markTourComplete(tourId);

                var config = window.EKWD_TOUR_CONFIG || {};
                if (!force && tourId === 'navigation' && config.autoNavigationTour && config.pageTourId === 'dashboard' && !isTourComplete('dashboard')) {
                    setTimeout(function () {
                        startTour('dashboard', false);
                    }, 600);
                }
            },
        });
    }

    var TOURS = {
        navigation: {
            id: 'navigation',
            steps: [
                {
                    popover: {
                        title: 'Welcome to eKWD-IUMS',
                        description: 'This tour walks through the exact sidebar menu order: Dashboard, then Files, Transactions, Process, Reports, and System Options.',
                        side: 'over',
                        align: 'center',
                    },
                },
                navigationSidebarStep(
                    '#sidebar-group-dashboard',
                    '#sidebar-nav-dashboard',
                    'Dashboard',
                    'Home screen with KPIs, charts, and district performance overview.'
                ),
                navigationSidebarStep(
                    '#sidebar-group-files',
                    '#sidebar-label-files',
                    'Files',
                    'Consumers, Import Consumer Master List, Category/Routes, Zone/Block, Services, and Sundies Account Title.'
                ),
                navigationSidebarStep(
                    '#sidebar-group-transactions',
                    '#sidebar-label-transactions',
                    'Transactions',
                    'Service Request, Billing Adjustment, Bill Payments/Collection, Import Collection, and Import LRO Ledger.'
                ),
                navigationSidebarStep(
                    '#sidebar-group-process',
                    '#sidebar-label-process',
                    'Process',
                    'Billing Process, Meter Reading Assignment, Download Reading, and Disconnection Management.'
                ),
                navigationSidebarStep(
                    '#sidebar-group-reports',
                    '#sidebar-label-reports',
                    'Reports',
                    'Billing Status, System Reports, and Disconnected consumer.'
                ),
                navigationSidebarStep(
                    '#sidebar-group-system-options',
                    '#sidebar-label-system-options',
                    'System Options',
                    'System Setting, Consumer Edit PIN, Manage Users, and Pricing Tiers.'
                ),
                {
                    element: '#tourHelpBtn',
                    onHighlightStarted: function () {
                        collapseAllSidebarMenus();
                    },
                    popover: {
                        title: 'Help & quick options',
                        description: 'Use the ? icon for System Guides, page tours, quick links to core modules, and reset tours.',
                        side: 'bottom',
                        align: 'end',
                    },
                },
            ],
        },

        dashboard: {
            id: 'dashboard',
            steps: [
                {
                    element: '.page-hero',
                    popover: {
                        title: 'Dashboard overview',
                        description: 'Your home screen for district performance — collections, billing activity, and consumer metrics.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '[data-tour="dashboard-kpis"]',
                    popover: {
                        title: 'Key metrics',
                        description: 'Live KPI cards show active consumers, collections, and other totals with month-over-month trend indicators.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '[data-tour="dashboard-charts"]',
                    popover: {
                        title: 'Charts',
                        description: 'Monthly water consumption trend and billing status breakdown. Use Reset/Fill on the consumption chart to adjust the view.',
                        side: 'top',
                        align: 'start',
                    },
                },
                sidebarSubmenuStep(
                    '#sidebar-group-process',
                    '#sidebar-label-process',
                    '#collapseTable',
                    'Start billing cycle',
                    'Each bill month begins at Process → Billing Process to prepare meter reading schedules.'
                ),
            ],
        },

        consumer: {
            id: 'consumer',
            steps: [
                {
                    element: '[data-tour="consumer-search"]',
                    popover: {
                        title: 'Search consumer',
                        description: 'Type account number, name, or meter number. Pick a suggestion to load that consumer\'s record below.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '[data-tour="consumer-new-btn"]',
                    popover: {
                        title: 'New application',
                        description: 'Register a new service connection. Fill in account details, zone, category, meter info, and address in the modal.',
                        side: 'bottom',
                        align: 'end',
                    },
                },
                {
                    element: '[data-tour="consumer-edit-btn"]',
                    popover: {
                        title: 'Edit',
                        description: 'Select a consumer first, then click Edit to update their record. Consumer Edit PIN may be required.',
                        side: 'bottom',
                        align: 'end',
                    },
                },
                {
                    element: '[data-tour="consumer-delete-btn"]',
                    popover: {
                        title: 'Delete',
                        description: 'Remove the selected consumer record. Only available after selecting a consumer from search.',
                        side: 'bottom',
                        align: 'end',
                    },
                },
                {
                    element: '#consumerInfoCard',
                    popover: {
                        title: 'Consumer details',
                        description: 'Account number, name, address, zone, meter, status, balance, and latest bill summary for the selected consumer.',
                        side: 'top',
                        align: 'start',
                    },
                },
                {
                    element: '[data-tour="consumer-meter-reading-card"]',
                    popover: {
                        title: 'Meter reading panel',
                        description: 'Shows the latest current and previous meter readings for this account, plus base reading setup for new connections.',
                        side: 'top',
                        align: 'start',
                    },
                },
                {
                    element: '[data-tour="consumer-current-reading"]',
                    popover: {
                        title: 'Current reading',
                        description: 'The most recent meter reading posted from field collection or billing. Date and value are read-only here.',
                        side: 'top',
                        align: 'start',
                    },
                },
                {
                    element: '[data-tour="consumer-previous-reading"]',
                    popover: {
                        title: 'Previous reading',
                        description: 'The prior bill period reading. When a schedule is active, you can correct the value and use Save Previous Reading below.',
                        side: 'top',
                        align: 'end',
                    },
                },
                {
                    element: '[data-tour="consumer-base-reading"]',
                    popover: {
                        title: 'Base reading',
                        description: 'For new accounts where the physical meter is not at zero. Sets the starting value used as previous reading on the first Meter Reading Preparation.',
                        side: 'top',
                        align: 'start',
                    },
                },
                {
                    element: '[data-tour="consumer-saved-base"]',
                    popover: {
                        title: 'Saved base',
                        description: 'Shows the stored base reading value and as-of date after you save. Displays dashes when no base reading has been set yet.',
                        side: 'left',
                        align: 'center',
                    },
                },
                {
                    element: '#meterReadingBaseValue',
                    popover: {
                        title: 'Base reading value',
                        description: 'Enter the actual dial reading on the meter when the account is first connected (e.g. 1500). Locked once billing history exists.',
                        side: 'top',
                        align: 'start',
                    },
                },
                {
                    element: '#meterReadingBaseDate',
                    popover: {
                        title: 'As of date',
                        description: 'The date when the base reading was taken. Saved together with the base reading value.',
                        side: 'top',
                        align: 'start',
                    },
                },
                {
                    element: '#meterReadingSaveBaseBtn',
                    popover: {
                        title: 'Save base reading',
                        description: 'Stores the base reading and date on the consumer record. Used automatically on the first bill month when there is no reading history yet.',
                        side: 'top',
                        align: 'end',
                    },
                },
                {
                    element: '#consumerDetailsTab',
                    popover: {
                        title: 'Navigation tabs',
                        description: 'Switch to Account Ledger, LRO Ledger, Service History, Meter Reading, Location Map, and Consumption for the same account.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                sidebarSubmenuStep(
                    '#sidebar-group-files',
                    '#sidebar-label-files',
                    '#collapseBootstrap',
                    'Bulk import',
                    'Use Files → Import Consumer Master List to load many consumers from Excel at once.'
                ),
            ],
        },

        'billing-processes': {
            id: 'billing-processes',
            steps: [
                {
                    element: '#tab-billing-processes',
                    popover: {
                        title: 'Billing Processes tab',
                        description: 'Run billing operations: prepare meter readings, apply surcharges, penalties, and bill printing.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '#tab-schedule-viewing',
                    popover: {
                        title: 'Schedule viewing tab',
                        description: 'Review saved meter reading schedule batches by zone and bill month after preparation.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '[data-tour="billing-quick-lookup"]',
                    popover: {
                        title: 'Quick account lookup',
                        description: 'Enter an account number to see the consumer name, zone, latest bill amount, and status without running a full process.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '[data-tour="billing-process-config"]',
                    popover: {
                        title: 'Process configuration',
                        description: 'Select Process Type, then set Zone, Bill Month, and account fields (for single/multiple consumer runs). Options change per process.',
                        side: 'right',
                        align: 'start',
                    },
                },
                {
                    element: '[data-tour="billing-dates-section"]',
                    popover: {
                        title: 'Billing dates',
                        description: 'Set Bill Date, Due Date, and Disconnection Date used when preparing meter reading schedules.',
                        side: 'right',
                        align: 'start',
                    },
                },
                {
                    element: '[data-tour="billing-process-actions"]',
                    popover: {
                        title: 'Process actions',
                        description: 'Execute Process runs the selected operation. Search Records loads existing data. Reset Form clears all inputs.',
                        side: 'left',
                        align: 'start',
                    },
                },
                {
                    element: '[data-tour="billing-records-toolbar"]',
                    popover: {
                        title: 'Records toolbar',
                        description: 'After preparing schedules: Save Schedules, Assign to reader, Apply Surcharge, Print, or Export. Use the table search to filter accounts.',
                        side: 'bottom',
                        align: 'end',
                    },
                },
                {
                    element: '#billingTableSearch',
                    popover: {
                        title: 'Table search',
                        description: 'Filter billing records by account number or account name in the results table.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                sidebarSubmenuStep(
                    '#sidebar-group-process',
                    '#sidebar-label-process',
                    '#collapseTable',
                    'Next step',
                    'After saving schedules, go to Meter Reading Assignment to assign routes to field readers.'
                ),
            ],
        },

        'meter-reading': {
            id: 'meter-reading',
            steps: [
                {
                    element: '[data-tour="meter-reading-tabs"]',
                    popover: {
                        title: 'Tabs',
                        description: 'Meter Readers — assignments and status. Field Findings, Miss Codes, and Install Psion cover device and field setup.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: 'button[data-target="#uploadPreviousReadingModal"]',
                    popover: {
                        title: 'Upload Previous Reading',
                        description: 'Bulk-update previous readings from an Excel file before starting the new bill month.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '[data-tour="meter-reading-actions"]',
                    popover: {
                        title: 'Assignment actions',
                        description: 'Assign to Reader — assign prepared schedules. Unassign Reader — remove assignments. Check Schedules — verify what is assigned.',
                        side: 'left',
                        align: 'start',
                    },
                },
                sidebarSubmenuStep(
                    '#sidebar-group-process',
                    '#sidebar-label-process',
                    '#collapseTable',
                    'Monitor uploads',
                    'Track reader progress under Process → Download Reading after readers upload from the mobile app.'
                ),
            ],
        },

        'download-reading': {
            id: 'download-reading',
            steps: [
                {
                    element: '[data-tour="download-reading-header"]',
                    popover: {
                        title: 'Realtime reading posting',
                        description: 'Monitor all meter readers and their upload progress for the current billing cycle.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '[data-tour="download-reader-search"]',
                    popover: {
                        title: 'Search reader',
                        description: 'Filter the reader list by name to quickly find a specific meter reader.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '#readersTable',
                    popover: {
                        title: 'Reader status table',
                        description: 'Columns show Total Routes, Pending, In Progress, and Completed counts per reader. Status updates live.',
                        side: 'top',
                        align: 'start',
                    },
                },
                {
                    element: '[data-tour="download-view-routes"]',
                    popover: {
                        title: 'View Routes',
                        description: 'Open detailed route list for a reader — filter by bill month, zone, status, or search by account/name.',
                        side: 'left',
                        align: 'start',
                    },
                },
            ],
        },

        disconnection: {
            id: 'disconnection',
            steps: [
                {
                    element: '[data-tour="disconnection-header"]',
                    popover: {
                        title: 'Disconnection management',
                        description: 'Identify delinquent accounts, create orders, generate notices, and track disconnected consumers.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '[data-tour="disconnection-tabs"]',
                    popover: {
                        title: 'Tabs',
                        description: 'Candidates — delinquent list. Saved / Assigned Orders — orders sent to mobile. Disconnected Only — completed disconnections.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '[data-tour="disconnection-filters"]',
                    popover: {
                        title: 'Filters',
                        description: 'Set Zone, Filter Type (disconnection date or consecutive unpaid months), Billing Month, Billing Date, or search text. Click Apply to load candidates.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: 'input[name="disconnection_date"]',
                    popover: {
                        title: 'Disconnection date',
                        description: 'Date when disconnection will take effect on saved orders and generated notices.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '#assignToDisconnector',
                    popover: {
                        title: 'Assign disconnector',
                        description: 'Choose the field disconnector for this batch, or leave Default for auto-assignment from system settings.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '[data-tour="disconnection-candidate-actions"]',
                    popover: {
                        title: 'Action buttons',
                        description: 'Print List — print filtered candidates. Save & Send to Mobile App — create orders for field execution. Generate Notice — preview disconnection notice document.',
                        side: 'top',
                        align: 'start',
                    },
                },
            ],
        },

        'billing-payment': {
            id: 'billing-payment',
            steps: [
                {
                    element: '[data-tour="payment-header"]',
                    popover: {
                        title: 'Payments & Collections',
                        description: 'Use this page to record Official Receipts (OR) and post collections to the consumer ledger.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '[data-tour="payment-header-fields"]',
                    popover: {
                        title: 'Transaction header',
                        description: 'Set Bill Month (MM-YYYY), transaction Date, and OR #. Enter an existing OR # to search or load a payment for update/delete. Payment status shows if the account is already paid for the selected month.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '#accountNumber',
                    popover: {
                        title: 'Account lookup',
                        description: 'Type an account number or consumer name, then pick from the suggestions list. The current balance, address, and bill breakdown will load automatically.',
                        side: 'right',
                        align: 'start',
                    },
                },
                {
                    element: '#viewLedgerBtn',
                    popover: {
                        title: 'View Ledger',
                        description: 'Opens the account ledger for the selected consumer so you can verify billings and prior payments before collecting.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '#paymentType',
                    popover: {
                        title: 'Payment type (required)',
                        description: 'Select how the customer paid: Cash, Check, GCash, Bank Transfer, or Palawan Pay-OTC. This field is required before saving.',
                        side: 'right',
                        align: 'start',
                    },
                },
                {
                    element: '#paymentRemarks',
                    popover: {
                        title: 'Remarks',
                        description: 'Optional notes for this collection (e.g. partial payment, check number, or special instructions).',
                        side: 'right',
                        align: 'start',
                    },
                },
                {
                    element: '[data-tour="payment-breakdown"]',
                    popover: {
                        title: 'Payment breakdown',
                        description: 'Review or adjust bill components: Current Bill, Arrears, Penalty, Maintenance, Senior Citizen discount, Advances, and Sundries. Subtotal and Overall Total update automatically.',
                        side: 'left',
                        align: 'start',
                    },
                },
                {
                    element: '#cashTendered',
                    popover: {
                        title: 'Cash tendered',
                        description: 'For cash payments, enter the amount given by the customer. Change is computed automatically from Total Payment.',
                        side: 'top',
                        align: 'start',
                    },
                },
                {
                    element: '#openBamSearchModalBtn',
                    popover: {
                        title: 'Search BAM No.',
                        description: 'Look up a Billing Adjustment Memo (BAM) number and apply sundry charges from the LRO ledger into the payment breakdown.',
                        side: 'top',
                        align: 'start',
                    },
                },
                {
                    element: '#resetFormBtn',
                    popover: {
                        title: 'Cancelled OR#',
                        description: 'Record a cancelled Official Receipt number without posting a new payment. Use when an OR was issued but the transaction was voided.',
                        side: 'top',
                        align: 'start',
                    },
                },
                {
                    element: '#updatePaymentBtn',
                    popover: {
                        title: 'Update Payment',
                        description: 'Load an existing payment by OR # or account, edit the breakdown or payment type, then save changes to the ledger.',
                        side: 'top',
                        align: 'center',
                    },
                },
                {
                    element: '#deletePaymentBtn',
                    popover: {
                        title: 'Delete Payment',
                        description: 'Remove a posted payment after loading it by OR #. Requires verification PIN. Use only to correct erroneous entries.',
                        side: 'top',
                        align: 'center',
                    },
                },
                {
                    element: '#savePaymentBtn',
                    popover: {
                        title: 'Save Payment',
                        description: 'Posts the collection to the consumer ledger when account, payment type, amounts, and OR # are complete. Review the status message below the form before clicking Save.',
                        side: 'top',
                        align: 'end',
                    },
                },
            ],
        },

        'billing-adjustment': {
            id: 'billing-adjustment',
            steps: [
                {
                    element: '#bamTabEntry',
                    popover: {
                        title: 'Entry tab',
                        description: 'Create a new Billing Adjustment Memo (BAM) for debit/credit corrections on consumer accounts.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '#bamTabList',
                    popover: {
                        title: 'List tab',
                        description: 'View, search, edit, or delete previously posted billing adjustments and LRO entries.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '#bamType',
                    popover: {
                        title: 'Type',
                        description: 'DM (Debit Memo) increases balance. CM (Credit Memo) decreases balance. Others for non-standard adjustments.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '#bamAr',
                    popover: {
                        title: 'AR / LRO',
                        description: 'AR posts to the consumer account ledger. LRO posts to the LRO ledger for sundry account corrections.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '#bamAccount',
                    popover: {
                        title: 'Account (required)',
                        description: 'Search by account number or consumer name. Select from suggestions — account name fills automatically.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '#bamAmount',
                    popover: {
                        title: 'Amount',
                        description: 'Enter the adjustment amount in pesos. Use positive values; type (DM/CM) determines debit or credit direction.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '#bamAcctCodeDisplay',
                    popover: {
                        title: 'Account code',
                        description: 'For LRO adjustments, select the sundry account code that classifies this entry.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '#bamRemarks',
                    popover: {
                        title: 'Remarks',
                        description: 'Explain the reason for the adjustment (e.g. meter error correction, billing dispute).',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '#bamStatus',
                    popover: {
                        title: 'Status',
                        description: 'Set whether the adjustment is Active or Cancelled after posting.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '#bamSaveBtn',
                    popover: {
                        title: 'Save',
                        description: 'Posts the BAM to the ledger. BAM number is assigned automatically unless editing an existing record.',
                        side: 'top',
                        align: 'end',
                    },
                },
                {
                    element: '#bamListSearch',
                    popover: {
                        title: 'List search',
                        description: 'On the List tab, search adjustments by account number, name, or BAM reference number.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
            ],
        },

        systemreport: {
            id: 'systemreport',
            steps: [
                {
                    element: '.reports-hero',
                    popover: {
                        title: 'System reports',
                        description: 'Library of operational and financial reports for billing reconciliation, collections, and consumer activity.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '[data-tour="report-cards"]',
                    popover: {
                        title: 'Report catalog',
                        description: 'Monthly Billing, Collection, Adjustment, Penalty, Consumer Ledger Card, and more. Click any card to open that report.',
                        side: 'top',
                        align: 'start',
                    },
                },
            ],
        },

        'billing-status': {
            id: 'billing-status',
            steps: [
                {
                    element: '[data-tour="billing-status-filter"]',
                    popover: {
                        title: 'Bill month filter',
                        description: 'Select the bill month to review, then click Refresh to load zone-level billing cycle status.',
                        side: 'bottom',
                        align: 'end',
                    },
                },
                {
                    element: '[data-tour="billing-status-table"]',
                    popover: {
                        title: 'Billing status table',
                        description: 'Per zone: Bill/Due/Discon dates and progress for Preparation, Reading Download, Upload, Posting, Bill Printing, Surcharge, and overall Status.',
                        side: 'top',
                        align: 'start',
                    },
                },
            ],
        },
    };

    function startTour(tourId, force) {
        var tour = TOURS[tourId];
        if (!tour) return false;

        if (tourId === 'navigation') {
            resetSidebarScroll();
            collapseAllSidebarMenus();
            clearTourNavHighlights();
        }

        var driverObj = createDriver(tour.steps, tour.id, !!force);
        if (!driverObj) return false;

        if (tourId === 'navigation') {
            setTimeout(function () {
                driverObj.drive();
            }, 250);
        } else {
            driverObj.drive();
        }
        return true;
    }

    function maybeAutoStart() {
        var config = window.EKWD_TOUR_CONFIG || {};
        var pageTourId = config.pageTourId;
        if (!pageTourId) return;

        if (config.autoNavigationTour && !isTourComplete('navigation')) {
            return;
        }

        if (isTourComplete(pageTourId)) return;

        setTimeout(function () {
            startTour(pageTourId, false);
        }, 900);
    }

    function bindHelpMenu() {
        var btn = document.getElementById('tourHelpBtn');
        var pageBtn = document.getElementById('tourPageBtn');
        var navBtn = document.getElementById('tourNavBtn');
        var resetBtn = document.getElementById('tourResetBtn');

        if (pageBtn) {
            pageBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var tourId = (window.EKWD_TOUR_CONFIG && window.EKWD_TOUR_CONFIG.pageTourId) || null;
                if (tourId) startTour(tourId, true);
            });
            var pageTourId = window.EKWD_TOUR_CONFIG && window.EKWD_TOUR_CONFIG.pageTourId;
            if (!pageTourId) {
                pageBtn.classList.add('disabled', 'text-muted');
                pageBtn.setAttribute('aria-disabled', 'true');
            }
        }

        if (navBtn) {
            navBtn.addEventListener('click', function (e) {
                e.preventDefault();
                startTour('navigation', true);
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', function (e) {
                e.preventDefault();
                localStorage.removeItem(storageKey);
                if (window.Swal) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Tours reset',
                        text: 'Page guides will show again on your next visit to each module.',
                        timer: 2500,
                        showConfirmButton: false,
                    });
                }
            });
        }

        if (btn) {
            btn.setAttribute('title', 'Help & guided tours');
        }
    }

    window.EKWD_TOURS = {
        start: startTour,
        markComplete: markTourComplete,
        isComplete: isTourComplete,
        resetAll: function () { localStorage.removeItem(storageKey); },
    };

    document.addEventListener('DOMContentLoaded', function () {
        bindHelpMenu();
        maybeAutoStart();

        var config = window.EKWD_TOUR_CONFIG || {};
        if (config.autoNavigationTour && !isTourComplete('navigation')) {
            setTimeout(function () {
                startTour('navigation', false);
            }, 1200);
        }
    });
})();
