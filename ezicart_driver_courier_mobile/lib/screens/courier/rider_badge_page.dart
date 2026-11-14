import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:qr_flutter/qr_flutter.dart';
import '../../config/theme.dart';
import '../../providers/auth_provider.dart';

class RiderBadgePage extends StatelessWidget {
  const RiderBadgePage({super.key});

  void _rotateBadge(BuildContext context) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
        title: const Text('Rotate Rider Badge?', style: TextStyle(fontWeight: FontWeight.bold)),
        content: const Text(
          'Rotating will invalidate previously printed or saved QR codes and generate a new verification token for hub check-ins.',
          style: TextStyle(fontSize: 13, color: AppTheme.muted),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: const Text('Cancel', style: TextStyle(color: AppTheme.muted)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: AppTheme.primary),
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text('Rotate Badge', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );

    if (confirm == true && context.mounted) {
      final auth = context.read<AuthProvider>();
      final ok = await auth.rotateBadge();
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(ok ? 'Rider badge rotated successfully!' : 'Failed to rotate badge.'),
            backgroundColor: ok ? AppTheme.success : AppTheme.danger,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final user = auth.user;
    final badgeCode = auth.badgeCode ?? user?.badgeCode ?? 'UNASSIGNED';
    final qrData = 'EZR:$badgeCode';

    return Scaffold(
      appBar: AppBar(
        title: const Text('Rider Identification Badge'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          children: [
            // Badge Card
            Container(
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: AppTheme.line),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.05),
                    blurRadius: 16,
                    offset: const Offset(0, 8),
                  ),
                ],
              ),
              child: Column(
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Image.asset(
                        'assets/images/ezicart-logo.png',
                        height: 36,
                        errorBuilder: (_, __, ___) => const Text(
                          'EZiCart',
                          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 20, color: AppTheme.primary),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: AppTheme.primarySoft,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: const Text(
                          'LOGISTICS RIDER',
                          style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: AppTheme.primary),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 20),

                  // Native QR Code
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: AppTheme.line),
                    ),
                    child: QrImageView(
                      data: qrData,
                      version: QrVersions.auto,
                      size: 200.0,
                      gapless: true,
                    ),
                  ),
                  const SizedBox(height: 16),

                  Text(
                    user?.name ?? 'Courier Rider',
                    style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: AppTheme.ink),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    user?.email ?? '',
                    style: const TextStyle(fontSize: 13, color: AppTheme.muted),
                  ),
                  const SizedBox(height: 12),
                  const Divider(color: AppTheme.line),
                  const SizedBox(height: 12),

                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceAround,
                    children: [
                      Column(
                        children: [
                          const Text('VEHICLE', style: TextStyle(fontSize: 10, color: AppTheme.muted, fontWeight: FontWeight.bold)),
                          const SizedBox(height: 2),
                          Text(
                            user?.vehicleType ?? user?.vehicleCategory ?? 'Standard',
                            style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: AppTheme.ink),
                          ),
                        ],
                      ),
                      Column(
                        children: [
                          const Text('PLATE NUMBER', style: TextStyle(fontSize: 10, color: AppTheme.muted, fontWeight: FontWeight.bold)),
                          const SizedBox(height: 2),
                          Text(
                            user?.plateNumber ?? 'N/A',
                            style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: AppTheme.ink),
                          ),
                        ],
                      ),
                      Column(
                        children: [
                          const Text('STATUS', style: TextStyle(fontSize: 10, color: AppTheme.muted, fontWeight: FontWeight.bold)),
                          const SizedBox(height: 2),
                          Text(
                            (user?.status ?? 'Approved').toUpperCase(),
                            style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: AppTheme.success),
                          ),
                        ],
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),

                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF1F5F9),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: SelectableText(
                      qrData,
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                        fontFamily: 'monospace',
                        color: AppTheme.ink,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),

            const Text(
              'Present this QR badge at Logistics sorting hubs and seller pickup checkpoints for physical scan-in and handoff verification.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 12, color: AppTheme.muted),
            ),
            const SizedBox(height: 20),

            OutlinedButton.icon(
              style: OutlinedButton.styleFrom(
                foregroundColor: AppTheme.primary,
                side: const BorderSide(color: AppTheme.primary),
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              icon: const Icon(Icons.sync_rounded),
              label: const Text('Rotate Rider Badge', style: TextStyle(fontWeight: FontWeight.bold)),
              onPressed: () => _rotateBadge(context),
            ),
          ],
        ),
      ),
    );
  }
}
