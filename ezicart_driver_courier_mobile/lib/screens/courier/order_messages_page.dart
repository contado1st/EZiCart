import 'dart:convert';
import 'package:flutter/material.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../services/api_service.dart';

class OrderMessagesPage extends StatefulWidget {
  final int orderId;
  final String orderNumber;

  const OrderMessagesPage({
    super.key,
    required this.orderId,
    required this.orderNumber,
  });

  @override
  State<OrderMessagesPage> createState() => _OrderMessagesPageState();
}

class _OrderMessagesPageState extends State<OrderMessagesPage> {
  bool _isLoading = true;
  List<Map<String, dynamic>> _contacts = [];
  int? _selectedRecipientId;
  final _messageCtrl = TextEditingController();
  bool _isSending = false;

  @override
  void initState() {
    super.initState();
    _loadContacts();
  }

  @override
  void dispose() {
    _messageCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadContacts() async {
    setState(() => _isLoading = true);
    try {
      final res = await ApiService.get(ApiConfig.orderMessagesEndpoint(widget.orderId));
      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        if (data['contacts'] is List) {
          _contacts = List<Map<String, dynamic>>.from(data['contacts']);
          if (_contacts.isNotEmpty) {
            _selectedRecipientId = _contacts.first['id'] as int;
          }
        }
      }
    } catch (_) {}
    if (mounted) setState(() => _isLoading = false);
  }

  Future<void> _sendMessage() async {
    final text = _messageCtrl.text.trim();
    if (text.isEmpty || _selectedRecipientId == null) return;

    setState(() => _isSending = true);
    try {
      final res = await ApiService.post(ApiConfig.orderMessagesEndpoint(widget.orderId), {
        'recipient_id': _selectedRecipientId,
        'body': text,
      });

      if (res.statusCode == 200) {
        _messageCtrl.clear();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Message sent successfully!'),
              backgroundColor: AppTheme.success,
            ),
          );
        }
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Failed to send message.'),
              backgroundColor: AppTheme.danger,
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error: $e'),
            backgroundColor: AppTheme.danger,
          ),
        );
      }
    }
    if (mounted) setState(() => _isSending = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Messages: ${widget.orderNumber}'),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.primary))
          : Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: AppTheme.primarySoft,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Row(
                      children: [
                        const Icon(Icons.info_outline_rounded, color: AppTheme.primary, size: 20),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            'Send a direct dispatch message to the participants involved in order ${widget.orderNumber}.',
                            style: const TextStyle(fontSize: 12, color: AppTheme.ink),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),

                  // Select Recipient
                  const Text(
                    'Select Recipient:',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppTheme.ink),
                  ),
                  const SizedBox(height: 8),
                  if (_contacts.isEmpty)
                    const Text('No eligible participants found for this order.', style: TextStyle(color: AppTheme.muted))
                  else
                    DropdownButtonFormField<int>(
                      initialValue: _selectedRecipientId,
                      decoration: const InputDecoration(
                        prefixIcon: Icon(Icons.person_outline_rounded),
                        contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                      ),
                      items: _contacts.map((c) {
                        final role = c['role']?.toString().toUpperCase() ?? '';
                        final name = c['name']?.toString() ?? 'Contact';
                        final shop = c['shop_name']?.toString();
                        return DropdownMenuItem<int>(
                          value: c['id'] as int,
                          child: Text(
                            '$name ($role)${shop != null ? ' - $shop' : ''}',
                            style: const TextStyle(fontSize: 13),
                          ),
                        );
                      }).toList(),
                      onChanged: (val) {
                        if (val != null) setState(() => _selectedRecipientId = val);
                      },
                    ),
                  const SizedBox(height: 16),

                  // Message Body
                  const Text(
                    'Your Message:',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppTheme.ink),
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _messageCtrl,
                    maxLines: 5,
                    decoration: const InputDecoration(
                      hintText: 'Type your message regarding this delivery or pickup...',
                      alignLabelWithHint: true,
                    ),
                  ),
                  const SizedBox(height: 16),

                  ElevatedButton.icon(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.primary,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                    ),
                    icon: _isSending
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                          )
                        : const Icon(Icons.send_rounded, size: 18),
                    label: const Text('Send Message', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                    onPressed: _isSending ? null : _sendMessage,
                  ),
                ],
              ),
            ),
    );
  }
}
