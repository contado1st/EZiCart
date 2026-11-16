import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../../../config/theme.dart';

/// Interactive modal sheet / dialog for live real-time QR code scanning
/// with torch toggle, camera switcher, and instant detection callback.
class LiveQrScannerDialog extends StatefulWidget {
  final String title;
  final String subtitle;
  final String? expectedPrefix;

  const LiveQrScannerDialog({
    super.key,
    this.title = 'Scan QR Code',
    this.subtitle = 'Align the QR code within the frame to scan',
    this.expectedPrefix,
  });

  static Future<String?> scan(
    BuildContext context, {
    String title = 'Scan QR Code',
    String subtitle = 'Align the QR code within the frame to scan',
    String? expectedPrefix,
  }) {
    return showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => LiveQrScannerDialog(
        title: title,
        subtitle: subtitle,
        expectedPrefix: expectedPrefix,
      ),
    );
  }

  @override
  State<LiveQrScannerDialog> createState() => _LiveQrScannerDialogState();
}

class _LiveQrScannerDialogState extends State<LiveQrScannerDialog> {
  late final MobileScannerController _scannerCtrl;
  bool _isTorchOn = false;
  bool _hasDetected = false;
  String? _detectedCode;

  @override
  void initState() {
    super.initState();
    _scannerCtrl = MobileScannerController(
      detectionSpeed: DetectionSpeed.normal,
      facing: CameraFacing.back,
      torchEnabled: false,
    );
  }

  @override
  void dispose() {
    _scannerCtrl.dispose();
    super.dispose();
  }

  void _onDetect(BarcodeCapture capture) {
    if (_hasDetected) return;
    for (final barcode in capture.barcodes) {
      final raw = barcode.rawValue?.trim();
      if (raw != null && raw.isNotEmpty) {
        setState(() {
          _hasDetected = true;
          _detectedCode = raw;
        });

        // Small delay to show visual confirmation before popping
        Future.delayed(const Duration(milliseconds: 350), () {
          if (mounted) {
            Navigator.of(context).pop(raw);
          }
        });
        break;
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.of(context).size;
    final scanBoxSize = (size.width * 0.68).clamp(200.0, 320.0);

    return Container(
      height: size.height * 0.82,
      decoration: const BoxDecoration(
        color: Color(0xFF18181B),
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: Column(
        children: [
          // Header Bar
          Container(
            padding: const EdgeInsets.fromLTRB(20, 14, 12, 12),
            decoration: const BoxDecoration(
              border: Border(
                bottom: BorderSide(color: Color(0xFF27272A), width: 1),
              ),
            ),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        widget.title,
                        style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.bold,
                          fontSize: 16,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        widget.subtitle,
                        style: const TextStyle(
                          color: Color(0xFFA1A1AA),
                          fontSize: 11,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ),
                ),
                // Torch toggle
                IconButton(
                  tooltip: 'Flashlight',
                  icon: Icon(
                    _isTorchOn ? Icons.flash_on_rounded : Icons.flash_off_rounded,
                    color: _isTorchOn ? Colors.amberAccent : Colors.white70,
                    size: 22,
                  ),
                  onPressed: () async {
                    await _scannerCtrl.toggleTorch();
                    setState(() {
                      _isTorchOn = !_isTorchOn;
                    });
                  },
                ),
                // Camera switcher
                IconButton(
                  tooltip: 'Switch Camera',
                  icon: const Icon(
                    Icons.flip_camera_ios_rounded,
                    color: Colors.white70,
                    size: 20,
                  ),
                  onPressed: () => _scannerCtrl.switchCamera(),
                ),
                // Close button
                IconButton(
                  tooltip: 'Close',
                  icon: const Icon(Icons.close_rounded, color: Colors.white70),
                  onPressed: () => Navigator.of(context).pop(),
                ),
              ],
            ),
          ),

          // Camera Viewport & Overlay
          Expanded(
            child: Stack(
              alignment: Alignment.center,
              children: [
                // Live Camera
                ClipRRect(
                  borderRadius: BorderRadius.circular(16),
                  child: MobileScanner(
                    controller: _scannerCtrl,
                    onDetect: _onDetect,
                    errorBuilder: (context, error) {
                      return Center(
                        child: Padding(
                          padding: const EdgeInsets.all(24.0),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              const Icon(
                                Icons.videocam_off_outlined,
                                size: 48,
                                color: Colors.white38,
                              ),
                              const SizedBox(height: 12),
                              Text(
                                'Camera error: ${error.errorCode}',
                                textAlign: TextAlign.center,
                                style: const TextStyle(
                                  color: Colors.white70,
                                  fontSize: 13,
                                ),
                              ),
                              const SizedBox(height: 8),
                              const Text(
                                'Please ensure camera permissions are granted or switch to manual input.',
                                textAlign: TextAlign.center,
                                style: TextStyle(
                                  color: Colors.white38,
                                  fontSize: 11,
                                ),
                              ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
                ),

                // Darkened cut-out frame with bounding box
                Container(
                  width: scanBoxSize,
                  height: scanBoxSize,
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(
                      color: _hasDetected ? AppTheme.success : AppTheme.primary,
                      width: 3,
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: (_hasDetected ? AppTheme.success : AppTheme.primary)
                            .withValues(alpha: 0.35),
                        blurRadius: 18,
                        spreadRadius: 2,
                      ),
                    ],
                  ),
                  child: Stack(
                    children: [
                      // Corner reticles
                      Align(
                        alignment: Alignment.topLeft,
                        child: _cornerIndicator(true, true),
                      ),
                      Align(
                        alignment: Alignment.topRight,
                        child: _cornerIndicator(true, false),
                      ),
                      Align(
                        alignment: Alignment.bottomLeft,
                        child: _cornerIndicator(false, true),
                      ),
                      Align(
                        alignment: Alignment.bottomRight,
                        child: _cornerIndicator(false, false),
                      ),
                    ],
                  ),
                ),

                // Feedback badge when scanned
                if (_hasDetected && _detectedCode != null)
                  Positioned(
                    bottom: 24,
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 16,
                        vertical: 10,
                      ),
                      decoration: BoxDecoration(
                        color: AppTheme.success,
                        borderRadius: BorderRadius.circular(12),
                        boxShadow: const [
                          BoxShadow(
                            color: Colors.black45,
                            blurRadius: 8,
                            offset: Offset(0, 3),
                          ),
                        ],
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(
                            Icons.check_circle_rounded,
                            color: Colors.white,
                            size: 18,
                          ),
                          const SizedBox(width: 8),
                          Text(
                            'Captured: $_detectedCode',
                            style: const TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.bold,
                              fontSize: 13,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
              ],
            ),
          ),

          // Bottom instruction info
          Container(
            padding: EdgeInsets.fromLTRB(
              20,
              12,
              20,
              MediaQuery.of(context).padding.bottom + 14,
            ),
            color: const Color(0xFF18181B),
            child: Row(
              children: [
                const Icon(
                  Icons.info_outline_rounded,
                  color: Color(0xFFA1A1AA),
                  size: 16,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    widget.expectedPrefix != null
                        ? 'Supports codes starting with "${widget.expectedPrefix}" or standard reference numbers.'
                        : 'Supports QR codes, Barcodes, and OTP delivery tokens.',
                    style: const TextStyle(
                      color: Color(0xFFA1A1AA),
                      fontSize: 11,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _cornerIndicator(bool top, bool left) {
    return Container(
      width: 20,
      height: 20,
      decoration: BoxDecoration(
        color: _hasDetected ? AppTheme.success : AppTheme.primary,
        borderRadius: BorderRadius.only(
          topLeft: top && left ? const Radius.circular(14) : Radius.zero,
          topRight: top && !left ? const Radius.circular(14) : Radius.zero,
          bottomLeft: !top && left ? const Radius.circular(14) : Radius.zero,
          bottomRight: !top && !left ? const Radius.circular(14) : Radius.zero,
        ),
      ),
    );
  }
}
