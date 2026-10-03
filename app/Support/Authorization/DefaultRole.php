<?php

namespace App\Support\Authorization;

/**
 * Default roles every tenant gets (ARCHITECTURE §3.2). They are system roles: read-only,
 * with permissions managed in code (each module lists which default roles get each permission).
 * Tenants create custom roles for anything else.
 */
enum DefaultRole: string
{
    case TenantOwner = 'tenant-owner';
    case GeneralManager = 'general-manager';
    case FrontOfficeManager = 'front-office-manager';
    case FrontDeskAgent = 'front-desk-agent';
    case ReservationAgent = 'reservation-agent';
    case HousekeepingSupervisor = 'housekeeping-supervisor';
    case MaintenanceTechnician = 'maintenance-technician';
    case FnbManager = 'fnb-manager';
    case OutletCashier = 'outlet-cashier';
    case Waiter = 'waiter';
    case Chef = 'chef';
    case Bartender = 'bartender';
    case StoreKeeper = 'store-keeper';
    case ProcurementOfficer = 'procurement-officer';
    case Accountant = 'accountant';
    case HrManager = 'hr-manager';
    case PayrollOfficer = 'payroll-officer';
    case Auditor = 'auditor';

    public function label(): string
    {
        return match ($this) {
            self::TenantOwner => __('Tenant Owner'),
            self::GeneralManager => __('General Manager'),
            self::FrontOfficeManager => __('Front Office Manager'),
            self::FrontDeskAgent => __('Front Desk Agent'),
            self::ReservationAgent => __('Reservation Agent'),
            self::HousekeepingSupervisor => __('Housekeeping Supervisor'),
            self::MaintenanceTechnician => __('Maintenance Technician'),
            self::FnbManager => __('F&B Manager'),
            self::OutletCashier => __('Outlet Cashier'),
            self::Waiter => __('Waiter / Captain'),
            self::Chef => __('Chef / Kitchen Staff'),
            self::Bartender => __('Bartender'),
            self::StoreKeeper => __('Store Keeper'),
            self::ProcurementOfficer => __('Procurement Officer'),
            self::Accountant => __('Accountant'),
            self::HrManager => __('HR Manager'),
            self::PayrollOfficer => __('Payroll Officer'),
            self::Auditor => __('Auditor'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::TenantOwner => __('Everything in the tenant, including subscription and billing.'),
            self::GeneralManager => __('All operational and financial modules for assigned properties; approvals.'),
            self::FrontOfficeManager => __('Reservations, front office, guests, folios, rates (view), reports.'),
            self::FrontDeskAgent => __('Create and modify reservations, check-in/out, take payments.'),
            self::ReservationAgent => __('Create and modify reservations, quotes, deposits.'),
            self::HousekeepingSupervisor => __('Room status, housekeeping tasks, lost & found.'),
            self::MaintenanceTechnician => __('Work orders assigned to them.'),
            self::FnbManager => __('Everything in the Restaurant module for assigned outlets.'),
            self::OutletCashier => __('POS sessions, settling bills, payments, charge to room.'),
            self::Waiter => __('Tables, orders, kitchen tickets and bills.'),
            self::Chef => __('Kitchen display and wastage.'),
            self::Bartender => __('Bar display and bar orders.'),
            self::StoreKeeper => __('Inventory: receive, issue, transfer, stock count.'),
            self::ProcurementOfficer => __('Requisitions, RFQs, purchase orders, vendors.'),
            self::Accountant => __('Accounting, expenses, vendor bills, payments, bank reconciliation.'),
            self::HrManager => __('Employees, attendance, leave, shifts.'),
            self::PayrollOfficer => __('Payroll runs, payslips, loans.'),
            self::Auditor => __('Read-only access to everything, including the audit log.'),
        };
    }

    /**
     * Local part of the demo user's email, e.g. frontdesk@rodelaresort.com.
     */
    public function demoMailbox(): string
    {
        return match ($this) {
            self::TenantOwner => 'owner',
            self::GeneralManager => 'gm',
            self::FrontOfficeManager => 'fomanager',
            self::FrontDeskAgent => 'frontdesk',
            self::ReservationAgent => 'reservations',
            self::HousekeepingSupervisor => 'housekeeping',
            self::MaintenanceTechnician => 'maintenance',
            self::FnbManager => 'fnb',
            self::OutletCashier => 'cashier',
            self::Waiter => 'waiter',
            self::Chef => 'chef',
            self::Bartender => 'bartender',
            self::StoreKeeper => 'store',
            self::ProcurementOfficer => 'procurement',
            self::Accountant => 'accountant',
            self::HrManager => 'hr',
            self::PayrollOfficer => 'payroll',
            self::Auditor => 'auditor',
        };
    }
}
