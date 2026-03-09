# Scheduling and Clinical API

## Appointments

- `GET /appointments` -> `ListAppointmentsQuery` -> Scheduling
- `POST /appointments` -> `CreateAppointmentCommand` -> Scheduling
- `GET /appointments/{appointmentId}` -> `GetAppointmentQuery` -> Scheduling
- `PATCH /appointments/{appointmentId}` -> `UpdateAppointmentCommand` -> Scheduling
- `DELETE /appointments/{appointmentId}` -> `DeleteAppointmentCommand` -> Scheduling
- `GET /appointments/search` -> `SearchAppointmentsQuery` -> Scheduling
- `GET /appointments/export` -> `ExportAppointmentsQuery` -> Scheduling
- `GET /appointments/{appointmentId}/audit` -> `GetAppointmentAuditQuery` -> Audit
- `POST /appointments/{appointmentId}:schedule` -> `ScheduleAppointmentCommand` -> Scheduling
- `POST /appointments/{appointmentId}:confirm` -> `ConfirmAppointmentCommand` -> Scheduling
- `POST /appointments/{appointmentId}:check-in` -> `CheckInAppointmentCommand` -> Scheduling
- `POST /appointments/{appointmentId}:start` -> `StartAppointmentCommand` -> Scheduling
- `POST /appointments/{appointmentId}:complete` -> `CompleteAppointmentCommand` -> Scheduling
- `POST /appointments/{appointmentId}:cancel` -> `CancelAppointmentCommand` -> Scheduling
- `POST /appointments/{appointmentId}:no-show` -> `MarkNoShowCommand` -> Scheduling
- `POST /appointments/{appointmentId}:reschedule` -> `RescheduleAppointmentCommand` -> Scheduling
- `POST /appointments/{appointmentId}:restore` -> `RestoreAppointmentCommand` -> Scheduling
- `GET /appointments/{appointmentId}/participants` -> `ListAppointmentParticipantsQuery` -> Scheduling
- `POST /appointments/{appointmentId}/participants` -> `AddAppointmentParticipantCommand` -> Scheduling
- `DELETE /appointments/{appointmentId}/participants/{participantId}` -> `RemoveAppointmentParticipantCommand` -> Scheduling
- `GET /appointments/{appointmentId}/notes` -> `ListAppointmentNotesQuery` -> Scheduling
- `POST /appointments/{appointmentId}/notes` -> `AddAppointmentNoteCommand` -> Scheduling
- `PATCH /appointments/{appointmentId}/notes/{noteId}` -> `UpdateAppointmentNoteCommand` -> Scheduling
- `DELETE /appointments/{appointmentId}/notes/{noteId}` -> `DeleteAppointmentNoteCommand` -> Scheduling
- `POST /appointments/{appointmentId}:send-reminder` -> `SendAppointmentReminderCommand` -> Notifications
- `POST /appointments/{appointmentId}:send-confirmation` -> `SendAppointmentConfirmationCommand` -> Notifications
- `POST /appointments/{appointmentId}:make-recurring` -> `MakeAppointmentRecurringCommand` -> Scheduling
- `POST /appointments/recurrences/{recurrenceId}:cancel` -> `CancelRecurrenceCommand` -> Scheduling
- `GET /waitlist` -> `ListWaitlistQuery` -> Scheduling
- `POST /waitlist` -> `AddToWaitlistCommand` -> Scheduling
- `DELETE /waitlist/{entryId}` -> `RemoveFromWaitlistCommand` -> Scheduling
- `POST /waitlist/{entryId}:offer-slot` -> `OfferWaitlistSlotCommand` -> Scheduling
- `POST /appointments/bulk` -> `BulkUpdateAppointmentsCommand` -> Scheduling
- `POST /appointments:bulk-cancel` -> `BulkCancelAppointmentsCommand` -> Scheduling
- `POST /appointments:bulk-reschedule` -> `BulkRescheduleAppointmentsCommand` -> Scheduling
- `POST /appointments:rebuild-cache` -> `RebuildSchedulingCacheCommand` -> Scheduling

## Treatment Plans and Encounters

- `GET /treatment-plans` -> `ListTreatmentPlansQuery` -> Treatment
- `POST /treatment-plans` -> `CreateTreatmentPlanCommand` -> Treatment
- `GET /treatment-plans/{planId}` -> `GetTreatmentPlanQuery` -> Treatment
- `PATCH /treatment-plans/{planId}` -> `UpdateTreatmentPlanCommand` -> Treatment
- `DELETE /treatment-plans/{planId}` -> `DeleteTreatmentPlanCommand` -> Treatment
- `GET /treatment-plans/search` -> `SearchTreatmentPlansQuery` -> Treatment
- `GET /treatment-plans/{planId}/items` -> `ListTreatmentItemsQuery` -> Treatment
- `POST /treatment-plans/{planId}/items` -> `AddTreatmentItemCommand` -> Treatment
- `PATCH /treatment-plans/{planId}/items/{itemId}` -> `UpdateTreatmentItemCommand` -> Treatment
- `DELETE /treatment-plans/{planId}/items/{itemId}` -> `RemoveTreatmentItemCommand` -> Treatment
- `POST /treatment-plans/{planId}:approve` -> `ApproveTreatmentPlanCommand` -> Treatment
- `POST /treatment-plans/{planId}:start` -> `StartTreatmentPlanCommand` -> Treatment
- `POST /treatment-plans/{planId}:pause` -> `PauseTreatmentPlanCommand` -> Treatment
- `POST /treatment-plans/{planId}:resume` -> `ResumeTreatmentPlanCommand` -> Treatment
- `POST /treatment-plans/{planId}:finish` -> `FinishTreatmentPlanCommand` -> Treatment
- `POST /treatment-plans/{planId}:reject` -> `RejectTreatmentPlanCommand` -> Treatment
- `GET /encounters` -> `ListEncountersQuery` -> Treatment
- `POST /encounters` -> `CreateEncounterCommand` -> Treatment
- `GET /encounters/{encounterId}` -> `GetEncounterQuery` -> Treatment
- `PATCH /encounters/{encounterId}` -> `UpdateEncounterCommand` -> Treatment
- `DELETE /encounters/{encounterId}` -> `DeleteEncounterCommand` -> Treatment
- `GET /encounters/{encounterId}/diagnoses` -> `ListDiagnosesQuery` -> Treatment
- `POST /encounters/{encounterId}/diagnoses` -> `AddDiagnosisCommand` -> Treatment
- `DELETE /encounters/{encounterId}/diagnoses/{dxId}` -> `RemoveDiagnosisCommand` -> Treatment
- `GET /encounters/{encounterId}/procedures` -> `ListProceduresQuery` -> Treatment
- `POST /encounters/{encounterId}/procedures` -> `AddProcedureCommand` -> Treatment
- `DELETE /encounters/{encounterId}/procedures/{procId}` -> `RemoveProcedureCommand` -> Treatment
- `GET /encounters/export` -> `ExportEncountersQuery` -> Treatment
- `POST /encounters/bulk` -> `BulkUpdateEncountersCommand` -> Treatment

## Labs

- `GET /lab-orders` -> `ListLabOrdersQuery` -> Lab
- `POST /lab-orders` -> `CreateLabOrderCommand` -> Lab
- `GET /lab-orders/{orderId}` -> `GetLabOrderQuery` -> Lab
- `PATCH /lab-orders/{orderId}` -> `UpdateLabOrderCommand` -> Lab
- `DELETE /lab-orders/{orderId}` -> `DeleteLabOrderCommand` -> Lab
- `GET /lab-orders/search` -> `SearchLabOrdersQuery` -> Lab
- `POST /lab-orders/{orderId}:send` -> `SendLabOrderCommand` -> Integrations
- `POST /lab-orders/{orderId}:cancel` -> `CancelLabOrderCommand` -> Lab
- `POST /lab-orders/{orderId}:mark-collected` -> `MarkSpecimenCollectedCommand` -> Lab
- `POST /lab-orders/{orderId}:mark-received` -> `MarkSpecimenReceivedCommand` -> Lab
- `POST /lab-orders/{orderId}:mark-complete` -> `MarkLabOrderCompleteCommand` -> Lab
- `GET /lab-orders/{orderId}/results` -> `ListLabResultsQuery` -> Lab
- `GET /lab-orders/{orderId}/results/{resultId}` -> `GetLabResultQuery` -> Lab
- `GET /lab-tests` -> `ListLabTestsQuery` -> Lab
- `POST /lab-tests` -> `CreateLabTestCommand` -> Lab
- `PATCH /lab-tests/{testId}` -> `UpdateLabTestCommand` -> Lab
- `DELETE /lab-tests/{testId}` -> `DeleteLabTestCommand` -> Lab
- `POST /webhooks/lab/{provider}` -> `ReceiveLabResultWebhookCommand` -> Integrations
- `POST /webhooks/lab/{provider}:verify` -> `VerifyLabWebhookCommand` -> Integrations
- `GET /lab-orders/export` -> `ExportLabOrdersQuery` -> Lab
- `POST /lab-orders/bulk` -> `BulkUpdateLabOrdersCommand` -> Lab
- `POST /lab-orders:reconcile` -> `ReconcileLabOrdersCommand` -> Lab

## Prescriptions and Medications

- `GET /prescriptions` -> `ListPrescriptionsQuery` -> Pharmacy
- `POST /prescriptions` -> `CreatePrescriptionCommand` -> Pharmacy
- `GET /prescriptions/{rxId}` -> `GetPrescriptionQuery` -> Pharmacy
- `PATCH /prescriptions/{rxId}` -> `UpdatePrescriptionCommand` -> Pharmacy
- `DELETE /prescriptions/{rxId}` -> `DeletePrescriptionCommand` -> Pharmacy
- `POST /prescriptions/{rxId}:issue` -> `IssuePrescriptionCommand` -> Pharmacy
- `POST /prescriptions/{rxId}:cancel` -> `CancelPrescriptionCommand` -> Pharmacy
- `POST /prescriptions/{rxId}:dispense` -> `DispensePrescriptionCommand` -> Pharmacy
- `GET /prescriptions/search` -> `SearchPrescriptionsQuery` -> Pharmacy
- `GET /prescriptions/export` -> `ExportPrescriptionsQuery` -> Pharmacy
- `GET /medications` -> `ListMedicationsQuery` -> Pharmacy
- `POST /medications` -> `CreateMedicationCommand` -> Pharmacy
- `GET /medications/{medId}` -> `GetMedicationQuery` -> Pharmacy
- `PATCH /medications/{medId}` -> `UpdateMedicationCommand` -> Pharmacy
- `DELETE /medications/{medId}` -> `DeleteMedicationCommand` -> Pharmacy
- `GET /medications/search` -> `SearchMedicationsQuery` -> Pharmacy
- `GET /patients/{patientId}/allergies` -> `ListAllergiesQuery` -> Pharmacy
- `POST /patients/{patientId}/allergies` -> `AddAllergyCommand` -> Pharmacy
- `DELETE /patients/{patientId}/allergies/{allergyId}` -> `RemoveAllergyCommand` -> Pharmacy
- `GET /patients/{patientId}/medications` -> `ListPatientMedicationsQuery` -> Pharmacy

## API Notes

- Scheduling owns availability and slot decisions.
- Provider availability rules are the canonical low-level schedule source for `T035`. Later provider work-hours and time-off flows must project onto the same rule engine instead of introducing a second competing schedule store.
- Availability slot reads are cache-aside in the tenant-scoped `availability` cache domain and must be explicitly invalidated when rules, clinic scheduling inputs, provider clinic assignment, or tenant timezone fallbacks change.
- Notifications may be invoked from appointment actions but remain in the Notifications module.
- Lab provider traffic must still pass through integration contracts.
