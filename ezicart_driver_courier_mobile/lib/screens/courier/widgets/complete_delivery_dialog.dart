import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../../../config/theme.dart';
import '../../../models/order_model.dart';

class CompleteDeliveryDialog extends StatefulWidget {
  final OrderModel order;

  const CompleteDeliveryDialog({super.key, required this.order});

  @override
  State<CompleteDeliveryDialog> createState() => _CompleteDeliveryDialogState();
}

class _CompleteDeliveryDialogState extends State<CompleteDeliveryDialog> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _recipientCtrl;
  final _codeCtrl = TextEditingController();
  late final TextEditingController _codCtrl;
  final _notesCtrl = TextEditingController();
  String? _pickedImagePath;

  @override
  void initState() {
    super.initState();
    _recipientCtrl = TextEditingController(text: widget.order.recipientName);
    _codCtrl = TextEditingController(
      text: widget.order.isCod ? widget.order.totalAmount.toStringAsFixed(2) : '',
    );
  }

  @override
  void dispose() {
    _recipientCtrl.dispose();
    _codeCtrl.dispose();
    _codCtrl.dispose();
    _notesCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickImage(ImageSource source) async {
    try {
      final picker = ImagePicker();
      final picked = await picker.pickImage(
        source: source,
        maxWidth: 1200,
        imageQuality: 85,
      );
      if (picked != null) {
        setState(() => _pickedImagePath = picked.path);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not access camera/gallery: $e')),
        );
      }
    }
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
              color: AppTheme.success.withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(8),
            ),
            child: const Icon(Icons.check_circle_rounded, color: AppTheme.success, size: 22),
          ),
          const SizedBox(width: 8),
          const Expanded(
            child: Text(
              'Complete Delivery',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
            ),
          ),
        ],
      ),
      content: SizedBox(
        width: double.maxFinite,
        child: SingleChildScrollView(
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
                Text(
                  'Destination: ${widget.order.deliveryAddress}',
                  style: const TextStyle(fontSize: 12, color: AppTheme.muted),
                ),
                const SizedBox(height: 14),

                // Recipient Confirmation Name
                TextFormField(
                  controller: _recipientCtrl,
                  decoration: const InputDecoration(
                    labelText: 'Recipient Name *',
                    hintText: 'Person receiving the parcel',
                    prefixIcon: Icon(Icons.person_outline_rounded),
                  ),
                  validator: (v) => (v == null || v.trim().isEmpty) ? 'Please enter recipient name' : null,
                ),
                const SizedBox(height: 12),

                // Buyer Delivery Code
                TextFormField(
                  controller: _codeCtrl,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                    labelText: 'Buyer Delivery Code *',
                    hintText: '6-digit OTP from recipient',
                    prefixIcon: Icon(Icons.lock_clock_outlined),
                  ),
                  validator: (v) => (v == null || v.trim().isEmpty) ? 'Buyer delivery code is required' : null,
                ),
                const SizedBox(height: 12),

                // COD Amount
                if (widget.order.isCod) ...[
                  TextFormField(
                    controller: _codCtrl,
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    decoration: InputDecoration(
                      labelText: 'Cash Collected (₱${widget.order.totalAmount.toStringAsFixed(2)}) *',
                      prefixIcon: const Icon(Icons.payments_outlined),
                      helperText: 'Must collect full order amount',
                    ),
                    validator: (v) {
                      if (v == null || v.trim().isEmpty) return 'Enter cash collected';
                      final amt = double.tryParse(v);
                      if (amt == null || (amt - widget.order.totalAmount).abs() > 0.01) {
                        return 'Amount must equal ₱${widget.order.totalAmount.toStringAsFixed(2)}';
                      }
                      return null;
                    },
                  ),
                  const SizedBox(height: 12),
                ] else ...[
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: AppTheme.success.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Row(
                      children: [
                        Icon(Icons.verified_rounded, color: AppTheme.success, size: 18),
                        SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            'Paid online. Do not collect cash.',
                            style: TextStyle(color: AppTheme.success, fontSize: 12, fontWeight: FontWeight.bold),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),
                ],

                // Delivery Notes
                TextFormField(
                  controller: _notesCtrl,
                  decoration: const InputDecoration(
                    labelText: 'Delivery Notes (Optional)',
                    hintText: 'e.g. Left at gate / front desk',
                    prefixIcon: Icon(Icons.notes_rounded),
                  ),
                ),
                const SizedBox(height: 14),

                // Proof Photo Upload
                const Text(
                  'Proof of Delivery Photo *',
                  style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: AppTheme.ink),
                ),
                const SizedBox(height: 6),
                if (_pickedImagePath != null)
                  Stack(
                    children: [
                      ClipRRect(
                        borderRadius: BorderRadius.circular(10),
                        child: Image.file(
                          File(_pickedImagePath!),
                          height: 130,
                          width: double.infinity,
                          fit: BoxFit.cover,
                        ),
                      ),
                      Positioned(
                        top: 6,
                        right: 6,
                        child: CircleAvatar(
                          backgroundColor: Colors.black54,
                          radius: 14,
                          child: IconButton(
                            padding: EdgeInsets.zero,
                            icon: const Icon(Icons.close_rounded, color: Colors.white, size: 16),
                            onPressed: () => setState(() => _pickedImagePath = null),
                          ),
                        ),
                      ),
                    ],
                  )
                else
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: () => _pickImage(ImageSource.camera),
                          icon: const Icon(Icons.camera_alt_rounded, size: 18),
                          label: const Text('Take Photo'),
                          style: OutlinedButton.styleFrom(
                            padding: const EdgeInsets.symmetric(vertical: 12),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: () => _pickImage(ImageSource.gallery),
                          icon: const Icon(Icons.photo_library_rounded, size: 18),
                          label: const Text('Gallery'),
                          style: OutlinedButton.styleFrom(
                            padding: const EdgeInsets.symmetric(vertical: 12),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                          ),
                        ),
                      ),
                    ],
                  ),
              ],
            ),
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
            backgroundColor: AppTheme.success,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
          ),
          onPressed: () {
            if (!_formKey.currentState!.validate()) return;
            if (_pickedImagePath == null) {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Please attach a proof of delivery photo.')),
              );
              return;
            }

            Navigator.of(context).pop({
              'recipient_confirmation': _recipientCtrl.text.trim(),
              'delivery_code': _codeCtrl.text.trim(),
              'cod_collected_amount': widget.order.isCod ? double.tryParse(_codCtrl.text.trim()) : null,
              'delivery_notes': _notesCtrl.text.trim(),
              'proof_file_path': _pickedImagePath!,
            });
          },
          child: const Text('Confirm Delivered', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
        ),
      ],
    );
  }
}
