# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release.

## 1.0.5 - TBD
### Changed
- Bootstrap: Upgraded to Bootstrap v5.1.3
- Reports: Blue sheet report adds hours to parent paycode.

## 1.0.4 - 2021-11-24
### Added
- United Way: Include United Way pledge form.  Module can be turned off via ACLs.


## 1.0.3 - 2021-11-12
### Changed
- Import: Fixed incorrect association with department during import.
- Notifications: Exited function if email proved NULL.  return was absent in 1.0.2.
- Timesheet: Preparers and above, governed by ACL, have ability to delete entire timecards.  Records are permanently deleted.
- Timesheet: Do not allow changes to completed time cards.

## 1.0.2 - 2021-11-05
### Changed
- Reports: Blue sheet report no longer displays blank lines for pay code.
- Reports: Time cards report consolidate multiple entries for same paycode.
- Timesheet: Unable to set status at or below current status, unless permission allows for unsubmit.
- Import: Update emails via Active Directory rather than import.
- Import: Import employees set status of Active and assign to department base on PTG alone.

## 1.0.1 - 2021-10-28
### Added
- Timesheet: Allow prepare-, approve-, and complete-all actions based on privileges.

### Changed
- Timesheet: Change text from 'Submit' to 'Add Paycode'.  Signature 'Submit' button was too close.
- Timesheet: Display error when application cannot establish user > employee relationship.
- Timesheet: List employees available to add in order by last name.  List includes departmental employees, not just employees authorized by the current user.
- Notifications: If adding a user's timesheet that doesn't have an AD account, upon signing and attempting notification, browser receives exception 5.1.1 User Known.  Added logger to catch notification errors.
- Reports: Include the total number of hours per employee, total rows.
- Reports: dept.phtml department dashboard specifies dev db.  needs to be generic, has to specify db due to ambiguity.
- Reports: Add DAYS to weekly time sheet dept. report
- Import: Parse timesheet using filename only.  Employee Number now contained in filenames.

## 1.0.0 - 2021-10-06


## 0.0.3 - 2020-10-27
### Added
- Added payroll controller to implement a payroll dashboard

## 0.0.2 - 2020-09-21
### Added
- Category to Paycode
- Blue Sheet Report for Department Preparer
- Backend Server identifier to layout footer

## 0.0.1 - 2020-09-14
### Added
- Full name to timesheet header for preparers to confirm which timesheet they're on.

### Changed
- Reduced size of Timesheet Week Ending Form to accomodate full name in header.