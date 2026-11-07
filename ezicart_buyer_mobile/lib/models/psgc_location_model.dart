class PsgcLocationModel {
  final String code;
  final String name;

  const PsgcLocationModel({
    required this.code,
    required this.name,
  });

  factory PsgcLocationModel.fromJson(Map<String, dynamic> json) {
    return PsgcLocationModel(
      code: json['code']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
    );
  }

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is PsgcLocationModel && runtimeType == other.runtimeType && code == other.code;

  @override
  int get hashCode => code.hashCode;

  @override
  String toString() => name;
}

