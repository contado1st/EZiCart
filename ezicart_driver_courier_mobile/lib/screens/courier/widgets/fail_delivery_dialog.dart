import 'package:flutter/material.dart';
import '../../../config/theme.dart';
import '../../../models/order_model.dart';

class FailDeliveryDialog extends StatefulWidget {
  final OrderModel order;

  const FailDeliveryDialog({super.key, required this.order});

  @override
  State<FailDeliveryDialog> createState() => _FailDeliveryDialogState();
}

class _FailDeliveryDialogState extends State<FailDeliveryDialog> {
  final _formKey = GlobalKey<FormState>();
  String _reason = 'recipient_unavailable';
  final _notesCtrl = TextEditingController();

  final Map<String, String> _reasons = {
    'recipient_unavailable': 'Recipient Unavailable',
    'incorrect_address': 'Incorrect / Incomplete Address',
    'recipient_refused': 'Recipient Refused Delivery',
    'unreachable_contact': 'Unreachable Contact Number',
    'access_issue': 'Access Issue / Gate Restricted',
    'damaged_parcel': 'Damaged Parcel',
    'other': 'Other Reason',
  };

  @override
  void dispose() {
    _notesCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      title: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(6),
            decoration: BoxDecoration(
              color: AppTheme.danger.withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(8),
            ),
            child: const Icon(Icons.warning_amber_rounded, color: AppTheme.danger, size: 22),
          ),
          const SizedBox(width: 8),
          const Expanded(
            child: Text(
              'Record Failed Attempt',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
            ),
          ),
        ],
      ),
      content: SingleChildScrollView(
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Order ${widget.order.orderNumber}',
                style: const TextStyle(fontWeight: FontWeight.bold, color: AppTheme.ink),
              ),
              const SizedBox(height: 4),
              const Text(
                'Recording a delivery attempt failure notifies Logistics and starts hub review.',
                style: TextStyle(fontSize: 12, color: AppTheme.muted),
              ),
              const SizedBox(height: 14),

              DropdownButtonFormField<String>(
                initialValue: _reason,
                decoration: const InputDecoration(
                  labelText: 'Failure Reason *',
                  prefixIcon: Icon(Icons.error_outline_rounded),
                ),
                items: _reasons.entries.map((e) {
                  return DropdownMenuItem(
                    value: e.key,
                    child: Text(e.value, style: const TextStyle(fontSize: 13)),
                  );
                }).toList(),
                onChanged: (val) {
                  if (val != null) setState(() => _reason = val);
                },
              ),
              const SizedBox(height: 14),

              TextFormField(
                controller: _notesCtrl,
                maxLines: 3,
                decoration: const InputDecoration(
                  labelText: 'Notes / Explanation (Optional)',
                  hintText: 'e.g. Called 3 times, neighbor said recipient is out of town',
                  alignLabelWithHint: true,
                ),
              ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(null),
          child: const Text('Cancel', style: TextStyle(color: AppTheme.muted)),
        ),
        ElevatedButton(
          style: ElevatedButton.styleFrom(
            backgroundColor: AppTheme.danger,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
          ),
          onPressed: () {
            if (!_formKey.currentState!.validate()) return;
            Navigator.of(context).pop({
              'failure_reason': _reason,
              'delivery_notes': _notesCtrl.text.trim(),
            });
          },
          child: const Text('Record Failure', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
        ),
      ],
    );
  }
}
