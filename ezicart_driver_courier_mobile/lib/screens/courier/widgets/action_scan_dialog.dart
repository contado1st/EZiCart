import 'package:flutter/material.dart';
import '../../../config/theme.dart';
import 'live_qr_scanner_dialog.dart';

class ActionScanDialog extends StatefulWidget {
  final String title;
  final String message;
  final String hint;
  final String defaultCode;
  final String confirmLabel;
  final Color? confirmColor;
  final bool isCodeRequired;

  const ActionScanDialog({
    super.key,
    required this.title,
    required this.message,
    this.hint = 'EZP:... or parcel code',
    this.defaultCode = '',
    this.confirmLabel = 'Confirm Scanned Parcel',
    this.confirmColor,
    this.isCodeRequired = true,
  });

  @override
  State<ActionScanDialog> createState() => _ActionScanDialogState();
}

class _ActionScanDialogState extends State<ActionScanDialog> {
  late final TextEditingController _codeCtrl;
  String _method = 'manual';

  @override
  void initState() {
    super.initState();
    _codeCtrl = TextEditingController(text: widget.defaultCode);
  }

  @override
  void dispose() {
    _codeCtrl.dispose();
    super.dispose();
  }

  Future<void> _startLiveScan() async {
    final scanned = await LiveQrScannerDialog.scan(
      context,
      title: widget.title,
      subtitle: 'Align the parcel QR code or barcode to scan',
      expectedPrefix: 'EZP:',
    );
    if (scanned != null && scanned.isNotEmpty && mounted) {
      setState(() {
        _codeCtrl.text = scanned;
        _method = 'camera';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
      title: Text(
        widget.title,
        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
      ),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              widget.message,
              style: const TextStyle(fontSize: 13, color: AppTheme.muted),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _codeCtrl,
              autofocus: true,
              autocorrect: false,
              enableSuggestions: false,
              decoration: InputDecoration(
                labelText: 'Parcel QR / Code',
                hintText: widget.hint,
                prefixIcon: const Icon(Icons.qr_code_scanner_rounded),
                suffixIcon: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (_codeCtrl.text.isNotEmpty)
                      IconButton(
                        icon: const Icon(Icons.clear_rounded, size: 18),
                        onPressed: () => setState(() => _codeCtrl.clear()),
                      ),
                    IconButton(
                      tooltip: 'Open Live QR Scanner',
                      icon: const Icon(Icons.camera_alt_rounded, color: AppTheme.primary, size: 20),
                      onPressed: _startLiveScan,
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                ActionChip(
                  avatar: Icon(
                    Icons.keyboard_outlined,
                    size: 16,
                    color: _method == 'manual' ? AppTheme.primary : AppTheme.muted,
                  ),
                  label: const Text('Manual Entry', style: TextStyle(fontSize: 11)),
                  backgroundColor: _method == 'manual' ? AppTheme.primarySoft : const Color(0xFFF1F5F9),
                  onPressed: () => setState(() => _method = 'manual'),
                ),
                const SizedBox(width: 8),
                ActionChip(
                  avatar: const Icon(
                    Icons.qr_code_scanner_rounded,
                    size: 16,
                    color: AppTheme.primary,
                  ),
                  label: const Text('Live Camera Scan', style: TextStyle(fontSize: 11)),
                  backgroundColor: AppTheme.primarySoft,
                  onPressed: _startLiveScan,
                ),
              ],
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(null),
          child: const Text('Cancel', style: TextStyle(color: AppTheme.muted)),
        ),
        ElevatedButton(
          style: ElevatedButton.styleFrom(
            backgroundColor: widget.confirmColor ?? AppTheme.primary,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
          ),
          onPressed: () {
            final code = _codeCtrl.text.trim();
            if (widget.isCodeRequired && code.isEmpty) {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Please scan or enter the parcel code.')),
              );
              return;
            }
            Navigator.of(context).pop({'code': code, 'method': _method});
          },
          child: Text(
            widget.confirmLabel,
            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
          ),
        ),
      ],
    );
  }
}
