// ignore_for_file: deprecated_member_use
import 'dart:convert';
import 'package:flutter/material.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/order_model.dart';
import '../../services/api_service.dart';

class CreateDisputePage extends StatefulWidget {
  final OrderModel order;

  const CreateDisputePage({super.key, required this.order});

  @override
  State<CreateDisputePage> createState() => _CreateDisputePageState();
}

class _CreateDisputePageState extends State<CreateDisputePage> {
  final _formKey = GlobalKey<FormState>();
  final _descCtrl = TextEditingController();

  final List<String> _reasons = [
    'Damaged Item',
    'Wrong Item Delivered',
    'Missing Items',
    'Non-Delivery',
    'Defective Goods',
    'Other',
  ];

  String? _selectedReason;
  bool _isSubmitting = false;

  @override
  void dispose() {
    _descCtrl.dispose();
    super.dispose();
  }

  Future<void> _submitDispute() async {
    if (!_formKey.currentState!.validate()) return;
    if (_selectedReason == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select a dispute reason.')),
      );
      return;
    }

    setState(() => _isSubmitting = true);
    try {
      final res = await ApiService.post(
        ApiConfig.orderDisputeEndpoint(widget.order.id),
        {
          'reason': _selectedReason,
          'description': _descCtrl.text.trim(),
        },
      );

      final body = jsonDecode(res.body);

      if (res.statusCode == 201 && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(body['message'] ?? 'Dispute ticket submitted successfully.'),
            backgroundColor: Colors.green,
          ),
        );
        Navigator.of(context).pop(true);
      } else if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(body['message'] ?? 'Failed to submit dispute.')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString())),
        );
      }
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Return & Dispute Request'),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // Info Banner
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: Colors.amber.shade50,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: Colors.amber.shade200),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Icon(Icons.info_outline, color: Colors.amber.shade900, size: 20),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          'Disputes are mediated by EZiCart administrators. Please provide clear details regarding your issue with Order #${widget.order.parcelCode}.',
                          style: TextStyle(color: Colors.amber.shade900, fontSize: 12, height: 1.3),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 20),

                // Order summary
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: Colors.grey.shade50,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: AppTheme.line),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text('Order #${widget.order.parcelCode}', style: const TextStyle(fontWeight: FontWeight.bold)),
                          Text(widget.order.statusLabel, style: const TextStyle(fontSize: 12, color: AppTheme.primary, fontWeight: FontWeight.w600)),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Text('Seller: ${widget.order.sellerName}', style: const TextStyle(fontSize: 12, color: AppTheme.muted)),
                      Text('Items: ${widget.order.items.length}', style: const TextStyle(fontSize: 12, color: AppTheme.muted)),
                    ],
                  ),
                ),
                const SizedBox(height: 20),

                // Reason dropdown
                DropdownButtonFormField<String>(
                  value: _selectedReason,
                  isExpanded: true,
                  decoration: const InputDecoration(
                    labelText: 'Reason for Return / Dispute *',
                    prefixIcon: Icon(Icons.report_problem_outlined, size: 20),
                  ),
                  hint: const Text('Choose reason'),
                  items: _reasons.map((r) {
                    return DropdownMenuItem(value: r, child: Text(r));
                  }).toList(),
                  onChanged: (v) => setState(() => _selectedReason = v),
                  validator: (v) => v == null ? 'Please select a reason' : null,
                ),
                const SizedBox(height: 16),

                // Description
                TextFormField(
                  controller: _descCtrl,
                  maxLines: 5,
                  decoration: const InputDecoration(
                    labelText: 'Detailed Explanation *',
                    hintText: 'Describe the problem with the item (e.g. damaged upon arrival, missing accessories, incorrect variation)...',
                    alignLabelWithHint: true,
                  ),
                  validator: (v) {
                    if (v == null || v.trim().length < 10) {
                      return 'Please provide at least 10 characters explaining the issue';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: 24),

                ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.red.shade700,
                    minimumSize: const Size(double.infinity, 48),
                  ),
                  onPressed: _isSubmitting ? null : _submitDispute,
                  child: _isSubmitting
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                        )
                      : const Text('Submit Dispute Case'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

