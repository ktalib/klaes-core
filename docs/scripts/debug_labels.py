from assign_grouping_metadata_optimized import OptimizedGroupingAssigner

def main():
    assigner = OptimizedGroupingAssigner()
    if not assigner.connect():
        return
    try:
        assigner.ensure_sufficient_labels(27000, dry_run=False)
    finally:
        assigner.disconnect()

if __name__ == "__main__":
    main()
