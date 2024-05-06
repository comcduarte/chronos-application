# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release.

## 1.1.2
- Remove restrictions on negative leave balances.

## 1.1.1
- Mobile Interface Update

## 1.1.0
- Added support for Box File storage.
- Increased Logging.
- Paperless Paystubs and other related files.

## 1.0.11
- Users have the ability to change pay code on line. 

## 1.0.10
### Added
- Leave Totals available on Timecard
- FMLA Pay Codes 

## 1.0.9
### Fixed
- Correction to Telestaff Import

## 1.0.8
### Fixed
- Corrected Department Lookup

## 1.0.7 - 2022-11-01
### Added
- Cron Action Controller for scheduled tasks.

### Changed
- Add hyperlink to report to open timecards.
- UploadFileForm has a RenameFile Input Filter, and we are removing the tmpname file, leaving upload in data file. Removed Input Filter.
- Include 001 hours per day in v2 report
- Upgraded Bootstrap to 5.2.1. Made changes to support compatibility.
- Moved Document Upload to Files Controller

## 1.0.6 - 2022-08-22
### Added

### Changed
- Include Detail Code in Notes field in Telestaff Import
- Custom Report dept_time_card_v2 sorts by Time Group, Tim Subgroup, and Employee Number descending respectively.
- Insert Page Breaks Between Employee. Keep all employee records from being split between pages when printing.
- Do not allow Telestaff Import to update timecards above Submitted Status

### Fixed

## 1.0.5 - 2022-05-13
### Added
- Import: Telestaff imported added to application for Police Department.

### Fixed
- Fix: Converted TUES and THURS references to standard three letter.

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