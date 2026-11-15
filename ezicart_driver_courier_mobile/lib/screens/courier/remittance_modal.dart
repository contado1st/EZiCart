import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../models/order_model.dart';
import '../../providers/courier_provider.dart';

class RemittanceModal extends StatefulWidget {
  final OrderModel order;

  const RemittanceModal({super.key, required this.order});

  @override
  State<RemittanceModal> createState() => _RemittanceModalState();
}

class _RemittanceModalState extends State<RemittanceModal> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _amountCtrl;
  final _refCtrl = TextEditingController();
  final _reasonCtrl = TextEditingController(text: 'COD delivery cash remittance');
  String? _pickedProofPath;

  @override
  void initState() {
    super.initState();
    _amountCtrl = TextEditingController(text: widget.order.totalAmount.toStringAsFixed(2));
  }

  @override
  void dispose() {
    _amountCtrl.dispose();
    _refCtrl.dispose();
    _reasonCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickProof(ImageSource source) async {
    try {
      final picker = ImagePicker();
      final picked = await picker.pickImage(source: source, maxWidth: 1200, imageQuality: 85);
      if (picked != null) {
        setState(() => _pickedProofPath = picked.path);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not access image: $e')),
        );
      }
    }
  }

  void _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_pickedProofPath == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please attach receipt or bank transfer screenshot.')),
      );
      return;
    }

    final courierProv = context.read<CourierProvider>();
    final amount = double.tryParse(_amountCtrl.text.trim()) ?? widget.order.totalAmount;

    final ok = await courierProv.submitRemittance(
      orderId: widget.order.id,
      amount: amount,
      reference: _refCtrl.text.trim(),
      reason: _reasonCtrl.text.trim(),
      evidencePath: _pickedProofPath!,
    );

    if (mounted) {
      if (ok) {
        Navigator.of(context).pop(true);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Remittance submitted! Logistics hub will verify receipt.'),
            backgroundColor: AppTheme.success,
          ),
        );
      } else if (courierProv.errorMessage != null) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(courierProv.errorMessage!),
            backgroundColor: AppTheme.danger,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;
    final courierProv = context.watch<CourierProvider>();

    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        top: 20,
        bottom: bottomInset + 20,
      ),
      child: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'Submit COD Remittance',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppTheme.ink),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close_rounded),
                    onPressed: () => Navigator.of(context).pop(false),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Text(
                'Order ${widget.order.orderNumber} · Total: ₱${widget.order.totalAmount.toStringAsFixed(2)}',
                style: const TextStyle(fontSize: 13, color: AppTheme.muted),
              ),
              const SizedBox(height: 16),

              // Amount
              TextFormField(
                controller: _amountCtrl,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: const InputDecoration(
                  labelText: 'Remittance Amount (₱) *',
                  prefixIcon: Icon(Icons.payments_outlined),
                ),
                validator: (v) {
                  if (v == null || v.trim().isEmpty) return 'Enter amount';
                  if (double.tryParse(v) == null) return 'Enter valid amount';
                  return null;
                },
              ),
              const SizedBox(height: 12),

              // Reference No.
              TextFormField(
                controller: _refCtrl,
                decoration: const InputDecoration(
                  labelText: 'Transaction Reference Number *',
                  hintText: 'e.g. GCash Ref / Bank Slip No.',
                  prefixIcon: Icon(Icons.receipt_long_rounded),
                ),
                validator: (v) => (v == null || v.trim().isEmpty) ? 'Please enter transaction reference number' : null,
              ),
              const SizedBox(height: 12),

              // Reason / Note
              TextFormField(
                controller: _reasonCtrl,
                decoration: const InputDecoration(
                  labelText: 'Notes / Explanation *',
                  prefixIcon: Icon(Icons.notes_rounded),
                ),
                validator: (v) => (v == null || v.trim().length < 5) ? 'Enter at least 5 characters' : null,
              ),
              const SizedBox(height: 14),

              // Proof Image
              const Text(
                'Remittance Evidence / Deposit Receipt *',
                style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: AppTheme.ink),
              ),
              const SizedBox(height: 8),
              if (_pickedProofPath != null)
                Stack(
                  children: [
                    ClipRRect(
                      borderRadius: BorderRadius.circular(10),
                      child: Image.file(
                        File(_pickedProofPath!),
                        height: 120,
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
                          onPressed: () => setState(() => _pickedProofPath = null),
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
                        onPressed: () => _pickProof(ImageSource.camera),
                        icon: const Icon(Icons.camera_alt_rounded, size: 18),
                        label: const Text('Camera'),
                        style: OutlinedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () => _pickProof(ImageSource.gallery),
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
              const SizedBox(height: 20),

              ElevatedButton(
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF0F766E),
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
                onPressed: courierProv.isLoading ? null : _submit,
                child: courierProv.isLoading
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                      )
                    : const Text(
                        'Submit Remittance',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Colors.white),
                      ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
