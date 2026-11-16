import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../models/order_model.dart';
import '../../providers/courier_provider.dart';
import 'order_details_page.dart';
import 'widgets/live_qr_scanner_dialog.dart';

class PackageCollectionScannerPage extends StatefulWidget {
  final OrderModel? order;
  final int? orderId;
  final String? expectedParcelCode;

  const PackageCollectionScannerPage({
    super.key,
    this.order,
    this.orderId,
    this.expectedParcelCode,
  });

  @override
  State<PackageCollectionScannerPage> createState() =>
      _PackageCollectionScannerPageState();
}

class _PackageCollectionScannerPageState
    extends State<PackageCollectionScannerPage> {
  final _codeCtrl = TextEditingController();
  String _scanMethod = 'camera'; // Default to camera for instant scan readiness
  bool _isSubmitting = false;
  String? _feedbackMessage;
  bool _isSuccessFeedback = false;
  late final MobileScannerController _cameraCtrl;
  bool _isTorchOn = false;
  DateTime? _lastScanTime;

  @override
  void initState() {
    super.initState();
    _cameraCtrl = MobileScannerController(
      detectionSpeed: DetectionSpeed.normal,
      facing: CameraFacing.back,
      torchEnabled: false,
    );

    if (widget.expectedParcelCode != null &&
        widget.expectedParcelCode!.isNotEmpty) {
      _codeCtrl.text = widget.expectedParcelCode!;
      _scanMethod = 'manual';
    } else {
      _scanMethod = 'camera';
    }

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        context.read<CourierProvider>().fetchDashboard();
      }
    });
  }

  void _setScanMethod(String method) {
    if (_scanMethod == method) return;
    setState(() {
      _scanMethod = method;
    });
    if (method == 'camera') {
      _cameraCtrl.start();
    } else {
      _cameraCtrl.stop();
    }
  }

  @override
  void dispose() {
    _cameraCtrl.dispose();
    _codeCtrl.dispose();
    super.dispose();
  }

  void _onDetect(BarcodeCapture capture) {
    if (_isSubmitting) return;
    final now = DateTime.now();
    if (_lastScanTime != null &&
        now.difference(_lastScanTime!).inMilliseconds < 2000) {
      return;
    }

    for (final barcode in capture.barcodes) {
      final raw = barcode.rawValue?.trim();
      if (raw != null && raw.isNotEmpty) {
        _lastScanTime = now;
        setState(() {
          _codeCtrl.text = raw;
          _scanMethod = 'camera';
        });
        _submitScan();
        break;
      }
    }
  }

  Future<void> _submitScan() async {
    final rawCode = _codeCtrl.text.trim();
    if (rawCode.isEmpty) {
      setState(() {
        _feedbackMessage = 'Please enter or scan a parcel reference code.';
        _isSuccessFeedback = false;
      });
      return;
    }

    setState(() {
      _isSubmitting = true;
      _feedbackMessage = null;
    });

    final courierProv = context.read<CourierProvider>();

    // 1. If an explicit order was provided
    if (widget.orderId != null) {
      final success = await courierProv.confirmPickup(
        widget.orderId!,
        rawCode,
        method: _scanMethod,
      );

      if (!mounted) return;
      setState(() {
        _isSubmitting = false;
        if (success) {
          _isSuccessFeedback = true;
          _feedbackMessage = 'Parcel collection confirmed successfully!';
        } else {
          _isSuccessFeedback = false;
          _feedbackMessage =
              courierProv.errorMessage ?? 'Failed to confirm parcel collection.';
        }
      });

      if (success) {
        Future.delayed(const Duration(milliseconds: 1200), () {
          if (mounted) Navigator.of(context).pop(true);
        });
      }
      return;
    }

    // 2. Generic Scan Mode: match against claimed pickups
    final claimed = courierProv.claimedPickups;
    OrderModel? matchedOrder;

    for (final o in claimed) {
      final targetCode = (o.parcelCode ?? '').trim().toLowerCase();
      final cleanInput = rawCode.trim().toLowerCase();
      if (targetCode.isNotEmpty &&
          (cleanInput == targetCode ||
              cleanInput == 'ezp:$targetCode' ||
              targetCode == 'ezp:$cleanInput')) {
        matchedOrder = o;
        break;
      }
      if (o.orderNumber.toLowerCase() == cleanInput) {
        matchedOrder = o;
        break;
      }
    }

    if (matchedOrder != null) {
      final success = await courierProv.confirmPickup(
        matchedOrder.id,
        rawCode,
        method: _scanMethod,
      );

      if (!mounted) return;
      setState(() {
        _isSubmitting = false;
        if (success) {
          _isSuccessFeedback = true;
          _feedbackMessage =
              'Confirmed collection for order ${matchedOrder!.orderNumber}!';
        } else {
          _isSuccessFeedback = false;
          _feedbackMessage =
              courierProv.errorMessage ?? 'Verification scan failed.';
        }
      });

      if (success) {
        Future.delayed(const Duration(milliseconds: 1400), () {
          if (mounted) Navigator.of(context).pop(true);
        });
      }
    } else {
      // 3. Fallback to direct backend scan matching
      final success = await courierProv.scanPickup(rawCode, method: _scanMethod);

      if (!mounted) return;
      setState(() {
        _isSubmitting = false;
        if (success) {
          _isSuccessFeedback = true;
          _feedbackMessage = courierProv.successMessage ??
              'Confirmed collection for parcel "$rawCode"!';
        } else {
          _isSuccessFeedback = false;
          _feedbackMessage = courierProv.errorMessage ??
              'No matching assigned pickup found for "$rawCode". Please review your assignments.';
        }
      });

      if (success) {
        Future.delayed(const Duration(milliseconds: 1400), () {
          if (mounted) Navigator.of(context).pop(true);
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final courierProv = context.watch<CourierProvider>();
    final activePickups = courierProv.claimedPickups;

    return Scaffold(
      backgroundColor: AppTheme.canvas,
      appBar: AppBar(
        flexibleSpace: Container(
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              colors: [Color(0xFFD63363), Color(0xFFBE2854)],
              begin: Alignment.centerLeft,
              end: Alignment.centerRight,
            ),
          ),
        ),
        backgroundColor: Colors.transparent,
        elevation: 0,
        iconTheme: const IconThemeData(color: Colors.white),
        title: Text(
          widget.order != null
              ? 'Scan Pickup: ${widget.order!.orderNumber}'
              : 'Package Collection Scanner',
          style: const TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.bold,
            fontSize: 16,
          ),
        ),
        actions: [
          IconButton(
            tooltip: 'Fullscreen Viewfinder',
            icon: const Icon(Icons.fullscreen_rounded, color: Colors.white),
            onPressed: () async {
              final scanned = await LiveQrScannerDialog.scan(
                context,
                title: 'Parcel QR / Barcode Scanner',
                subtitle: 'Point at the shipping label or merchant package',
                expectedPrefix: 'EZP:',
              );
              if (scanned != null && scanned.isNotEmpty && mounted) {
                setState(() {
                  _codeCtrl.text = scanned;
                  _scanMethod = 'camera';
                });
                _submitScan();
              }
            },
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Target Order Banner if specific order passed
            if (widget.order != null) ...[
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: AppTheme.line),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const Icon(Icons.inventory_2_outlined,
                            color: AppTheme.primary, size: 20),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            widget.order!.orderNumber,
                            style: const TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 15,
                              color: AppTheme.ink,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: AppTheme.primarySoft,
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            widget.order!.statusLabel,
                            style: const TextStyle(
                              color: AppTheme.primary,
                              fontSize: 10,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const Divider(height: 18),
                    Text(
                      'Seller: ${widget.order!.sellerName}',
                      style: const TextStyle(
                          fontSize: 13, fontWeight: FontWeight.w600),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Pickup: ${widget.order!.sellerAddress}',
                      style:
                          const TextStyle(fontSize: 12, color: AppTheme.muted),
                    ),
                    if (widget.expectedParcelCode != null &&
                        widget.expectedParcelCode!.isNotEmpty) ...[
                      const SizedBox(height: 6),
                      Text(
                        'Expected Label: ${widget.expectedParcelCode}',
                        style: const TextStyle(
                          fontSize: 12,
                          color: AppTheme.primary,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 16),
            ],

            // Scanner Card
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: AppTheme.line),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.03),
                    blurRadius: 10,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Row(
                    children: [
                      Icon(Icons.qr_code_scanner_rounded,
                          color: AppTheme.primary, size: 22),
                      SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'Confirm Scanned Parcel Possession',
                          style: TextStyle(
                            fontWeight: FontWeight.bold,
                            fontSize: 15,
                            color: AppTheme.ink,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Point the camera at the parcel QR code / barcode, or enter the parcel code manually.',
                    style: TextStyle(fontSize: 12, color: AppTheme.muted),
                  ),
                  const SizedBox(height: 16),

                  // Mode Selector Tabs
                  Container(
                    padding: const EdgeInsets.all(4),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF4F4F5),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Row(
                      children: [
                        Expanded(
                          child: InkWell(
                            onTap: () => _setScanMethod('camera'),
                            child: Container(
                              padding: const EdgeInsets.symmetric(vertical: 8),
                              decoration: BoxDecoration(
                                color: _scanMethod == 'camera'
                                    ? Colors.white
                                    : Colors.transparent,
                                borderRadius: BorderRadius.circular(8),
                                boxShadow: _scanMethod == 'camera'
                                    ? [
                                        BoxShadow(
                                          color: Colors.black
                                              .withValues(alpha: 0.05),
                                          blurRadius: 4,
                                        ),
                                      ]
                                    : null,
                              ),
                              child: Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(
                                    Icons.camera_alt_rounded,
                                    size: 16,
                                    color: _scanMethod == 'camera'
                                        ? AppTheme.primary
                                        : AppTheme.muted,
                                  ),
                                  const SizedBox(width: 6),
                                  Text(
                                    'Live Camera',
                                    style: TextStyle(
                                      fontSize: 12,
                                      fontWeight: FontWeight.bold,
                                      color: _scanMethod == 'camera'
                                          ? AppTheme.primary
                                          : AppTheme.muted,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                        Expanded(
                          child: InkWell(
                            onTap: () => _setScanMethod('manual'),
                            child: Container(
                              padding: const EdgeInsets.symmetric(vertical: 8),
                              decoration: BoxDecoration(
                                color: _scanMethod == 'manual'
                                    ? Colors.white
                                    : Colors.transparent,
                                borderRadius: BorderRadius.circular(8),
                                boxShadow: _scanMethod == 'manual'
                                    ? [
                                        BoxShadow(
                                          color: Colors.black
                                              .withValues(alpha: 0.05),
                                          blurRadius: 4,
                                        ),
                                      ]
                                    : null,
                              ),
                              child: Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(
                                    Icons.keyboard_outlined,
                                    size: 16,
                                    color: _scanMethod == 'manual'
                                        ? AppTheme.primary
                                        : AppTheme.muted,
                                  ),
                                  const SizedBox(width: 6),
                                  Text(
                                    'Manual Input',
                                    style: TextStyle(
                                      fontSize: 12,
                                      fontWeight: FontWeight.bold,
                                      color: _scanMethod == 'manual'
                                          ? AppTheme.primary
                                          : AppTheme.muted,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),

                  // Embedded Live Camera Viewfinder when camera selected
                  if (_scanMethod == 'camera') ...[
                    Container(
                      height: 230,
                      decoration: BoxDecoration(
                        color: Colors.black,
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: AppTheme.primary, width: 2),
                      ),
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(12),
                        child: Stack(
                          alignment: Alignment.center,
                          children: [
                            MobileScanner(
                              controller: _cameraCtrl,
                              onDetect: _onDetect,
                              errorBuilder: (context, error) {
                                return Center(
                                  child: Padding(
                                    padding: const EdgeInsets.all(16),
                                    child: Column(
                                      mainAxisSize: MainAxisSize.min,
                                      children: [
                                        const Icon(
                                          Icons.videocam_off_outlined,
                                          color: Colors.white54,
                                          size: 32,
                                        ),
                                        const SizedBox(height: 8),
                                        Text(
                                          'Camera unavailable (${error.errorCode})',
                                          style: const TextStyle(
                                            color: Colors.white70,
                                            fontSize: 12,
                                          ),
                                        ),
                                        TextButton(
                                          onPressed: () => _setScanMethod('manual'),
                                          child: const Text('Use Manual Entry'),
                                        ),
                                      ],
                                    ),
                                  ),
                                );
                              },
                            ),
                            // Scanning frame target box
                            Container(
                              width: 170,
                              height: 170,
                              decoration: BoxDecoration(
                                borderRadius: BorderRadius.circular(14),
                                border: Border.all(
                                  color: AppTheme.primary,
                                  width: 2.5,
                                ),
                              ),
                            ),
                            // Top overlay controls
                            Positioned(
                              top: 8,
                              right: 8,
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  CircleAvatar(
                                    radius: 16,
                                    backgroundColor: Colors.black54,
                                    child: IconButton(
                                      padding: EdgeInsets.zero,
                                      iconSize: 18,
                                      tooltip: 'Flashlight',
                                      icon: Icon(
                                        _isTorchOn
                                            ? Icons.flash_on
                                            : Icons.flash_off,
                                        color: _isTorchOn
                                            ? Colors.amberAccent
                                            : Colors.white,
                                      ),
                                      onPressed: () async {
                                        await _cameraCtrl.toggleTorch();
                                        setState(() {
                                          _isTorchOn = !_isTorchOn;
                                        });
                                      },
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  CircleAvatar(
                                    radius: 16,
                                    backgroundColor: Colors.black54,
                                    child: IconButton(
                                      padding: EdgeInsets.zero,
                                      iconSize: 18,
                                      tooltip: 'Switch Camera',
                                      icon: const Icon(
                                        Icons.flip_camera_ios,
                                        color: Colors.white,
                                      ),
                                      onPressed: () =>
                                          _cameraCtrl.switchCamera(),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 14),
                  ],

                  // Input Field
                  TextField(
                    controller: _codeCtrl,
                    autofocus: _scanMethod == 'manual',
                    autocorrect: false,
                    enableSuggestions: false,
                    decoration: InputDecoration(
                      labelText: 'Parcel Code / Label Reference *',
                      hintText: 'e.g. EZP:000105 or ORD-98765',
                      prefixIcon: const Icon(Icons.qr_code_2_rounded),
                      suffixIcon: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          if (_codeCtrl.text.isNotEmpty)
                            IconButton(
                              icon: const Icon(Icons.clear_rounded, size: 18),
                              onPressed: () {
                                _codeCtrl.clear();
                                setState(() {});
                              },
                            ),
                          IconButton(
                            tooltip: 'Fullscreen Scanner',
                            icon: const Icon(Icons.qr_code_scanner_rounded,
                                color: AppTheme.primary, size: 20),
                            onPressed: () async {
                              final scanned = await LiveQrScannerDialog.scan(
                                context,
                                title: 'Parcel QR / Barcode Scanner',
                                subtitle: 'Scan parcel shipping label',
                                expectedPrefix: 'EZP:',
                              );
                              if (scanned != null &&
                                  scanned.isNotEmpty &&
                                  mounted) {
                                setState(() {
                                  _codeCtrl.text = scanned;
                                  _scanMethod = 'camera';
                                });
                                _submitScan();
                              }
                            },
                          ),
                        ],
                      ),
                    ),
                    onChanged: (_) => setState(() {}),
                  ),
                  const SizedBox(height: 16),

                  // Feedback banner if any
                  if (_feedbackMessage != null) ...[
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: _isSuccessFeedback
                            ? const Color(0xFFECFDF5)
                            : const Color(0xFFFFF1F2),
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(
                          color: _isSuccessFeedback
                              ? const Color(0xFFA7F3D0)
                              : const Color(0xFFFECDD3),
                        ),
                      ),
                      child: Row(
                        children: [
                          Icon(
                            _isSuccessFeedback
                                ? Icons.check_circle_rounded
                                : Icons.error_outline_rounded,
                            color: _isSuccessFeedback
                                ? AppTheme.success
                                : AppTheme.danger,
                            size: 18,
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              _feedbackMessage!,
                              style: TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                                color: _isSuccessFeedback
                                    ? const Color(0xFF065F46)
                                    : const Color(0xFF9F1239),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                  ],

                  // Confirm Scan Button
                  ElevatedButton.icon(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.primary,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                      ),
                    ),
                    icon: _isSubmitting
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : const Icon(Icons.check_rounded, size: 20),
                    label: Text(
                      _isSubmitting
                          ? 'Verifying Parcel...'
                          : 'Confirm Parcel Possession',
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    onPressed: _isSubmitting ? null : _submitScan,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),

            // Pending Pickups List Helper (if no specific order given)
            if (widget.order == null && activePickups.isNotEmpty) ...[
              const Text(
                'Parcels Waiting For Collection',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.bold,
                  color: AppTheme.ink,
                ),
              ),
              const SizedBox(height: 8),
              ListView.separated(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                itemCount: activePickups.length,
                separatorBuilder: (_, __) => const SizedBox(height: 8),
                itemBuilder: (context, index) {
                  final item = activePickups[index];
                  final parcelRef = item.parcelCode ?? item.orderNumber;
                  final isSelected =
                      _codeCtrl.text.trim().toLowerCase() ==
                          parcelRef.toLowerCase() ||
                      _codeCtrl.text.trim().toLowerCase() ==
                          'ezp:${parcelRef.toLowerCase()}';

                  return InkWell(
                    onTap: () {
                      setState(() {
                        _codeCtrl.text = parcelRef;
                        _scanMethod = 'manual';
                        _feedbackMessage = null;
                      });
                    },
                    borderRadius: BorderRadius.circular(12),
                    child: Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: isSelected
                            ? AppTheme.primarySoft
                            : Colors.white,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(
                          color: isSelected
                              ? AppTheme.primary
                              : AppTheme.line,
                          width: isSelected ? 1.5 : 1,
                        ),
                      ),
                      child: Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.all(8),
                            decoration: BoxDecoration(
                              color: AppTheme.primarySoft,
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: const Icon(
                              Icons.local_shipping_outlined,
                              color: AppTheme.primary,
                              size: 18,
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    Expanded(
                                      child: Text(
                                        item.orderNumber,
                                        style: const TextStyle(
                                          fontWeight: FontWeight.bold,
                                          fontSize: 13,
                                          color: AppTheme.ink,
                                        ),
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    ),
                                    if (item.parcelCode != null)
                                      Container(
                                        padding: const EdgeInsets.symmetric(
                                          horizontal: 6,
                                          vertical: 2,
                                        ),
                                        decoration: BoxDecoration(
                                          color: Colors.grey.shade100,
                                          borderRadius:
                                              BorderRadius.circular(4),
                                        ),
                                        child: Text(
                                          item.parcelCode!,
                                          style: const TextStyle(
                                            fontFamily: 'monospace',
                                            fontSize: 10,
                                            fontWeight: FontWeight.bold,
                                            color: AppTheme.ink,
                                          ),
                                        ),
                                      ),
                                  ],
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  'Merchant: ${item.sellerName}',
                                  style: const TextStyle(
                                    fontSize: 11,
                                    color: AppTheme.muted,
                                  ),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                ),
                                Text(
                                  item.sellerAddress,
                                  style: const TextStyle(
                                    fontSize: 10,
                                    color: AppTheme.muted,
                                  ),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(width: 8),
                          IconButton(
                            icon: const Icon(
                              Icons.arrow_forward_ios_rounded,
                              size: 14,
                              color: AppTheme.muted,
                            ),
                            onPressed: () {
                              Navigator.of(context).push(
                                MaterialPageRoute(
                                  builder: (_) =>
                                      OrderDetailsPage(orderId: item.id),
                                ),
                              );
                            },
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
            ],
          ],
        ),
      ),
    );
  }
}
