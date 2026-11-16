import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';

class WorkAvailabilityPage extends StatefulWidget {
  const WorkAvailabilityPage({super.key});

  @override
  State<WorkAvailabilityPage> createState() => _WorkAvailabilityPageState();
}

class _WorkAvailabilityPageState extends State<WorkAvailabilityPage> {
  bool _isLoading = true;
  bool _isSaving = false;
  String _currentState = 'available';
  int _currentVersion = 0;
  bool _enforced = false;
  String? _changedAt;
  String? _message;
  bool _isError = false;

  @override
  void initState() {
    super.initState();
    _fetchAvailability();
  }

  Future<void> _fetchAvailability() async {
    setState(() => _isLoading = true);
    try {
      final res = await ApiService.get(ApiConfig.courierWorkAvailabilityEndpoint);
      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        if (mounted) {
          setState(() {
            _currentState = data['work_availability']?.toString() ?? 'available';
            _currentVersion = (data['version'] is num) ? (data['version'] as num).toInt() : 0;
            _enforced = data['enforced'] == true;
            _changedAt = data['changed_at']?.toString();
            _isLoading = false;
          });
          return;
        }
      }
    } catch (_) {}
    if (mounted) setState(() => _isLoading = false);
  }

  Future<void> _toggleAvailability(String newState) async {
    if (newState == _currentState) return;

    if (newState == 'unavailable') {
      final confirm = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Row(
            children: [
              Icon(Icons.pause_circle_outline_rounded, color: AppTheme.warning),
              SizedBox(width: 8),
              Text('Go Off Duty?'),
            ],
          ),
          content: const Text(
            'Marking yourself unavailable will withdraw any unaccepted dispatch offers or pickup proposals back to logistics. Any work you have already accepted will remain assigned to you.',
            style: TextStyle(fontSize: 13, height: 1.4),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancel'),
            ),
            ElevatedButton(
              style: ElevatedButton.styleFrom(backgroundColor: AppTheme.warning),
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Confirm Offline', style: TextStyle(color: Colors.white)),
            ),
          ],
        ),
      );
      if (confirm != true) return;
    }

    setState(() {
      _isSaving = true;
      _message = null;
    });

    try {
      final res = await ApiService.post(ApiConfig.courierWorkAvailabilityEndpoint, {
        'state': newState,
        'version': _currentVersion,
      });

      final data = jsonDecode(res.body);
      if (res.statusCode == 200 && data['success'] == true) {
        setState(() {
          _currentState = data['work_availability']?.toString() ?? newState;
          _currentVersion = (data['version'] is num) ? (data['version'] as num).toInt() : _currentVersion + 1;
          _enforced = data['enforced'] == true;
          _isSaving = false;
          _isError = false;
          _message = data['message']?.toString() ?? 'Work availability updated.';
        });
        if (mounted) {
          context.read<AuthProvider>().refreshUser();
        }
      } else {
        setState(() {
          _isSaving = false;
          _isError = true;
          _message = data['message']?.toString() ?? 'Failed to update availability.';
        });
      }
    } catch (e) {
      setState(() {
        _isSaving = false;
        _isError = true;
        _message = e.toString();
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final isAvailable = _currentState.toLowerCase() == 'available';

    return Scaffold(
      appBar: AppBar(
        title: const Text('Work Availability', style: TextStyle(fontWeight: FontWeight.bold)),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.primary))
          : RefreshIndicator(
              onRefresh: _fetchAvailability,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (_message != null) ...[
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: _isError ? const Color(0xFFFFF1F2) : const Color(0xFFECFDF5),
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(
                          color: _isError ? const Color(0xFFFECDD3) : const Color(0xFFA7F3D0),
                        ),
                      ),
                      child: Row(
                        children: [
                          Icon(
                            _isError ? Icons.error_outline : Icons.check_circle_outline,
                            color: _isError ? AppTheme.danger : AppTheme.success,
                            size: 20,
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              _message!,
                              style: TextStyle(
                                fontSize: 13,
                                color: _isError ? const Color(0xFF9F1239) : const Color(0xFF065F46),
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                  ],

                  // Status Card
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: isAvailable ? AppTheme.success.withValues(alpha: 0.3) : AppTheme.warning.withValues(alpha: 0.3)),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.04),
                          blurRadius: 10,
                          offset: const Offset(0, 4),
                        ),
                      ],
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.center,
                      children: [
                        Container(
                          width: 72,
                          height: 72,
                          decoration: BoxDecoration(
                            color: isAvailable ? const Color(0xFFECFDF5) : const Color(0xFFFFFBEB),
                            shape: BoxShape.circle,
                          ),
                          child: Icon(
                            isAvailable ? Icons.check_circle_rounded : Icons.pause_circle_filled_rounded,
                            color: isAvailable ? AppTheme.success : AppTheme.warning,
                            size: 40,
                          ),
                        ),
                        const SizedBox(height: 14),
                        Text(
                          isAvailable ? 'Available for New Work' : 'Currently Unavailable',
                          style: TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.bold,
                            color: isAvailable ? const Color(0xFF065F46) : const Color(0xFF92400E),
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          isAvailable
                              ? 'You are active on dispatch. Automated cycles and sorting hubs can assign pickups and doorstep deliveries to you.'
                              : 'You are off duty. Logistics will not route new automated offers or unaccepted tasks to your queues.',
                          textAlign: TextAlign.center,
                          style: const TextStyle(fontSize: 13, color: AppTheme.muted, height: 1.4),
                        ),
                        if (_changedAt != null) ...[
                          const SizedBox(height: 8),
                          Text(
                            'Last updated: $_changedAt${_enforced ? ' (Enforced by Admin)' : ''}',
                            style: TextStyle(
                              fontSize: 11,
                              color: _enforced ? AppTheme.danger : AppTheme.muted,
                              fontWeight: _enforced ? FontWeight.bold : FontWeight.normal,
                            ),
                          ),
                        ],
                        const SizedBox(height: 20),

                        // Switch Buttons
                        Row(
                          children: [
                            Expanded(
                              child: OutlinedButton(
                                style: OutlinedButton.styleFrom(
                                  backgroundColor: isAvailable ? AppTheme.success : Colors.white,
                                  side: const BorderSide(color: AppTheme.success),
                                  padding: const EdgeInsets.symmetric(vertical: 12),
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                ),
                                onPressed: _isSaving ? null : () => _toggleAvailability('available'),
                                child: Text(
                                  'Available',
                                  style: TextStyle(
                                    fontWeight: FontWeight.bold,
                                    color: isAvailable ? Colors.white : AppTheme.success,
                                  ),
                                ),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: OutlinedButton(
                                style: OutlinedButton.styleFrom(
                                  backgroundColor: !isAvailable ? AppTheme.warning : Colors.white,
                                  side: const BorderSide(color: AppTheme.warning),
                                  padding: const EdgeInsets.symmetric(vertical: 12),
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                ),
                                onPressed: _isSaving ? null : () => _toggleAvailability('unavailable'),
                                child: Text(
                                  'Unavailable',
                                  style: TextStyle(
                                    fontWeight: FontWeight.bold,
                                    color: !isAvailable ? Colors.white : AppTheme.warning,
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 20),

                  // Guidelines and Operational Notice
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: AppTheme.surface,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: AppTheme.line),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Row(
                          children: [
                            Icon(Icons.info_outline_rounded, size: 18, color: AppTheme.info),
                            SizedBox(width: 8),
                            Text('How Availability Works', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                          ],
                        ),
                        const SizedBox(height: 10),
                        _buildRuleItem('Accepted Tasks Retained', 'Switching to unavailable does not release parcels currently in your vehicle custody or tasks you already confirmed.'),
                        const SizedBox(height: 8),
                        _buildRuleItem('Provisional Offers Withdrawn', 'Unaccepted automatic dispatch offers and unclaimed pickup requests are immediately released back to the sorting hub.'),
                        const SizedBox(height: 8),
                        _buildRuleItem('Real-Time Multi-Platform Sync', 'Your availability status is instantly updated on the web Logistics Control Tower and Dispatch board.'),
                      ],
                    ),
                  ),
                ],
              ),
            ),
    );
  }

  Widget _buildRuleItem(String title, String body) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Padding(
          padding: EdgeInsets.only(top: 4),
          child: Icon(Icons.circle, size: 6, color: AppTheme.primary),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: RichText(
            text: TextSpan(
              style: const TextStyle(fontSize: 12, color: AppTheme.ink, height: 1.4),
              children: [
                TextSpan(text: '$title: ', style: const TextStyle(fontWeight: FontWeight.bold)),
                TextSpan(text: body, style: const TextStyle(color: AppTheme.muted)),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
